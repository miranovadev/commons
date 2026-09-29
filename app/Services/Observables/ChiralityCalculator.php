<?php

namespace App\Services\Observables;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\ObservableDirection;
use App\Enums\SupersessionStrength;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Services\AttestationEvidenceService;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class ChiralityCalculator
{
    public function __construct(
        private AttestationEvidenceService $evidence,
    ) {}

    public function calculate(LineageComparison $comparison): float
    {
        return $this->components($comparison)->average();
    }

    public function components(LineageComparison $comparison): ChiralityValues
    {
        [$currentActs, $currentCycles] = $this->population($comparison->current);
        [$previousActs, $previousCycles] = $comparison->previous === null ? [0, 0] : $this->population($comparison->previous);
        $orientation = $comparison->previous === null ? 0.0
            : round($this->lean($comparison->current) * $this->lean($comparison->previous), 7);

        return new ChiralityValues($orientation,
            ChiralityValues::similarity($currentActs, $currentCycles, $previousActs, $previousCycles),
            $currentCycles, $previousCycles, $currentActs, $previousActs);
    }

    /** Every entry in a represented basin counts, including Cycles with no contributions. */
    private function population(DomainLineage $lineage): array
    {
        $context = $lineage->context;
        $entries = $lineage->propositions->map(fn ($p) => $p->phase->cycle->entry_committal_id)
            ->merge($lineage->propositions->where('tier', 'committal')->pluck('id'))->filter()->unique();
        // Origin's actual producing Cycle is the sole null-entry population.
        // Never count all null-entry Cycles or infer participation from acts.
        $origins = $lineage->propositions->filter(fn ($p) => $p->tier === 'committal'
            && $p->phase->cycle->entry_committal_id === null
            && $lineage->horizon->parent_context_horizon_id === null)
            ->map(fn ($p) => $p->phase->cycle->id)->unique();
        $load = function () use ($entries, $context) {
            $ids = $context === null ? $entries : $context->propositions
                ->map(fn ($p) => $p->phase->cycle->entry_committal_id)
                ->push($context->phase->cycle->entry_committal_id)
                ->merge($context->propositions->where('tier', 'committal')->pluck('id'))->filter()->unique()->merge($entries)->unique();

            return Cycle::whereIn('entry_committal_id', $ids)->selectRaw('entry_committal_id, context_horizon_id, count(*) as aggregate')
                ->groupBy('entry_committal_id', 'context_horizon_id')->get()
                ->mapWithKeys(fn ($row) => [$row->entry_committal_id.':'.$row->context_horizon_id => (int) $row->aggregate]);
        };
        $counts = $context === null ? $load() : $context->remember('chirality:entry-counts', $load);
        $cycles = $origins->count() + $entries->sum(fn ($id) => (int) $counts->get($id.':'.$lineage->horizon->id, 0));
        $ids = $lineage->propositions->pluck('id');
        $acts = $context === null ? Attestation::whereIn('proposition_id', $ids)->count()
            : $context->attestationsFor($ids->all())->count();

        return [$acts, $cycles];
    }

    private function lean(DomainLineage $lineage): float
    {
        $ids = $this->activeIds($lineage->propositions, $lineage->supersessions);

        return $this->evidence->valueFor($lineage->propositions->whereIn('id', $ids), $lineage->context);
    }

    /** Shared hard-supersession semantics for active propositions in a lineage. */
    public function activeIds(Collection $propositions, Collection $supersessions): Collection
    {
        $supersededIds = $supersessions
            ->filter(
                fn ($edge) => SupersessionStrength::for($edge->type)
                    === SupersessionStrength::Hard,
            )
            ->pluck('source_proposition_id')
            ->unique();

        return $propositions->pluck('id')->diff($supersededIds)->values();
    }

    /** Receipt-neutral effective Attestation lean, before cross-domain comparison. */
    public function leanFor(Collection $activeIds, ?ParticipationContext $context = null): float
    {
        return $this->evidence->valueFor($context !== null ? $context->nodes($activeIds) : Proposition::query()->whereIn('id', $activeIds)->get(), $context);
    }

    /** Effective Attestation direction reconstructed from evidence in its fixed region frame. */
    public function directionFor(Proposition $proposition, ?ParticipationContext $context = null): ObservableDirection
    {
        return $this->evidence->directionFor($proposition, context: $context);
    }
}
