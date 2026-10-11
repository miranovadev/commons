<?php

namespace App\Services;

use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\CommittalLineageService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\Participation\ParticipationContext;

class ClosureGateService
{
    public function __construct(
        private readonly ProspectiveClosureService $states,
        private readonly CommittalLineageService $lineage,
    ) {}

    public function preview(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): array
    {
        $evaluate = function () use ($gate): array {
            try {
                return $this->states->stored($gate);
            } catch (ClosureHistoryUnavailable) {
                return ['depth' => null, 'cadence' => null, 'closure' => null,
                    'historical_extent' => null, 'historical_rhythm' => null,
                    'historical_extent_direction' => null, 'historical_rhythm_direction' => null,
                    'admissible' => false, 'unavailable_reason' => 'prospective_closure_unavailable'];
            }
        };

        return $context === null ? $evaluate() : $context->remember('closure:'.$phase->id.':'.$gate->id, $evaluate);
    }

    public function forCommitment(Proposition $target): array
    {
        return $this->states->stored($target);
    }

    /** The creating Cycle's entry Committal is the sole committed directional predecessor. */
    public function historyFor(Proposition $position, ?ParticipationContext $context = null, ?ContextHorizon $horizon = null): ?CommitmentSnapshot
    {
        return $this->lineage->ancestors($position, $context)->first();
    }
}
