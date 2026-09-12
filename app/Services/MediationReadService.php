<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Enums\RegisterPosition;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class MediationReadService
{
    public function __construct(
        private PropositionMeasurementService $measurements,
        private CommitRegionService $regions,
    ) {}

    /** The previous Disperse Gate is the anchor, regardless of display ordering. */
    public function anchorFor(Phase $phase): ?Proposition
    {
        if ($phase->kind !== PhaseKind::Mediate) {
            return null;
        }

        $disperse = $phase->cycle->phases()
            ->where('kind', PhaseKind::Disperse->value)
            ->where('id', '<', $phase->id)
            ->whereNotNull('completed_at')->latest('id')->first();
        $anchor = $disperse?->gate;

        return $anchor?->phase->cycle->participant->substrate_id === $phase->cycle->participant->substrate_id
            ? $anchor : null;
    }

    /** Anchor first, then corresponding Reads across the Commit Region. */
    public function readsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $context !== null
            ? $context->remember('mediate-reads:'.$phase->id, fn () => $this->resolveReads($phase, $context))
            : $this->resolveReads($phase);
    }

    private function resolveReads(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $anchor = $this->anchorFor($phase);
        if ($anchor === null || $anchor->tier !== 'expressive'
            || RegisterPosition::forRegister($anchor->register) !== RegisterPosition::Third
            || $this->regions->seedFor($anchor, $context) === null) {
            return collect();
        }
        $reads = collect([$anchor]);
        $direction = $this->measurements->correspondenceDirectionFor($anchor, $context);

        $candidates = $this->regions->forPhase($phase, $anchor, $context)->where('id', '!=', $anchor->id);

        return $reads->concat($candidates->filter(fn (Proposition $candidate): bool => $this->measurements->correspondenceDirectionFor($candidate, $context) === $direction))->values();
    }

    /** Immediate Gate children remain reusable by competing reconciliations. */
    public function gatesFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $reads = $this->readsFor($phase, $context);
        if ($reads->isEmpty()) {
            return collect();
        }
        $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->firstOrFail();

        $pool = $this->regions->atPosition($phase, $gate, RegisterPosition::First, $context, includeSuperseded: true);

        return Proposition::query()->whereKey($pool->pluck('id'))
            ->whereHas('parents', fn ($query) => $query->whereKey($reads->pluck('id')))
            ->orderBy('id')->get();
    }
}
