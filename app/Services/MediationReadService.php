<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class MediationReadService
{
    public function __construct(
        private PropositionMeasurementService $measurements,
        private CommitRegionService $regions,
        private PhasePositionService $positions,
        private MediationAnchorService $anchors,
        private HomologousMediationService $homology,
    ) {}

    public function anchorFor(Phase $phase): ?Proposition
    {
        return $this->anchors->anchorFor($phase);
    }

    /** Participant context exposes only the causal anchor; regional Reads stay internal. */
    public function visibleReadsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        return $this->readsFor($phase, $context)->take(1)->values();
    }

    /** Internal comparison pool: anchor first, then corresponding regional Reads. */
    public function readsFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Mediate) {
            return collect();
        }
        $context ??= new ParticipationContext($phase);

        return $context->remember('mediate-reads:'.$phase->id, fn () => $this->resolveReads($phase, $context));
    }

    private function resolveReads(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $anchor = $this->anchorFor($phase);
        if ($anchor === null || ! $this->positions->matches($anchor, $phase, PhaseKind::Constrain)
            || $this->regions->seedFor($anchor, $context) === null) {
            return collect();
        }
        $reads = collect([$anchor]);
        $direction = $this->measurements->correspondenceDirectionFor($anchor, $context);

        $candidates = $this->regions->forPhase($phase, $anchor, $context)->where('id', '!=', $anchor->id);

        return $reads->concat($candidates->filter(fn (Proposition $candidate): bool => $this->measurements->correspondenceDirectionFor($candidate, $context) === $direction))
            ->concat($this->homology->poolFor($phase, $context)['reads'])->unique('id')->values();
    }

    /** Immediate Gate children remain reusable by competing reconciliations. */
    public function gatesFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Mediate) {
            return collect();
        }
        $context ??= new ParticipationContext($phase);
        $reads = $this->readsFor($phase, $context);
        if ($reads->isEmpty()) {
            return collect();
        }
        $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate()->firstOrFail();

        $pool = $this->regions->atPhasePosition($phase, $gate, PhaseKind::Disperse, $context, includeSuperseded: true);

        $readIds = $reads->pluck('id')->flip();

        return $context->nodes($pool->pluck('id'))->filter(fn ($node) => $context->activeIds->has($node->id)
            && $context->parents->get($node->id, collect())->contains(fn ($edge) => $readIds->has($edge->source_proposition_id)))
            ->sortBy('id')->concat($this->homology->poolFor($phase, $context)['gates'])
            ->unique('id')->sortBy('id')->values();
    }
}
