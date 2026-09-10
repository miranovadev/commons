<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Observables\ChiralityCalculator;
use Illuminate\Support\Collection;

final readonly class ReconciliationOptionService
{
    public function __construct(
        private LineageService $lineage,
        private PropositionMeasurementService $measurements,
        private ChiralityCalculator $chirality,
        private MediationReadService $mediation,
    ) {}

    /** Mediate derives Gates from Reads; Bias retains its current family lean rule. */
    public function forPhase(Phase $phase): Collection
    {
        if ($phase->write_register === null
            || ! in_array($phase->kind, [PhaseKind::Mediate, PhaseKind::Bias], true)) {
            return collect();
        }
        if ($phase->kind === PhaseKind::Mediate) {
            $gates = $this->mediation->gatesFor($phase);

            return $gates->contains('id', $phase->gate_proposition_id)
                ? $gates->where('id', '!=', $phase->gate_proposition_id)->values()
                : collect();
        }
        $gate = $phase->gate()->with('contextHorizonMembership')->firstOrFail();
        $horizonId = $gate->contextHorizonMembership?->context_horizon_id;
        if ($horizonId === null) {
            return collect();
        }
        $substrate = $phase->cycle->participant->substrate;
        $parents = $gate->parents()->whereHas('phase.cycle.participant', fn ($query) => $query
            ->where('substrate_id', $substrate->id))->get();
        $excluded = $gate->children()->pluck('propositions.id')->merge($parents->pluck('id'))->push($gate->id);
        $this->measurements->repairMissing(collect([$gate]));
        $options = collect();

        // Evaluate each shared-parent context separately, including multi-parent Gates.
        foreach ($parents as $parent) {
            $family = $this->lineage->descendantsInHorizon($parent, $horizonId);
            $edges = Supersession::query()->whereIn('source_proposition_id', $family->pluck('id'))
                ->whereIn('target_proposition_id', $family->pluck('id'))->get();
            $activeIds = $this->chirality->activeIds($family, $edges);
            $active = $family->whereIn('id', $activeIds)->where('tier', 'expressive');
            $siblingIds = $this->lineage->immediateDescendantIds($parent);
            $candidates = $active->whereIn('id', $siblingIds)->whereNotIn('id', $excluded)
                ->where('register', $gate->register)->values();
            $dominant = $this->direction($this->chirality->leanFor($active->pluck('id'), $substrate));
            $options = $options->concat($candidates->filter(fn (Proposition $candidate): bool =>
                $dominant !== 0 && $this->direction($this->chirality->leanFor(collect([$candidate->id]), $substrate)) === $dominant));
        }
        return $options->unique('id')->sortBy('id')
            ->take(max(1, (int) config('schemas.options.limit', 10)))->values();
    }

    private function direction(float|string|null $value): int
    {
        return $value === null ? 0 : ((float) $value <=> 0.0);
    }
}
