<?php

namespace App\Services;

use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ClosureBirthService;
use App\Services\Observables\CommittalLineageService;
use App\Services\Participation\ParticipationContext;

class ClosureGateService
{
    public function __construct(
        private readonly ClosureBirthService $births,
        private readonly CommittalLineageService $lineage,
    ) {}

    public function preview(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): array
    {
        $evaluate = function () use ($phase): array {
            // Producing-Cycle governance, never the expressive Gate's birth state.
            $candidate = new Proposition;
            $candidate->forceFill(['tier' => 'committal']);
            $candidate->setRelation('phase', $phase);
            try {
                if ($phase->cycle->entry_committal_id === null) {
                    throw new ClosureHistoryUnavailable('A prospective Commit requires its producing Cycle entry Committal.');
                }

                return $this->births->prospective($candidate);
            } catch (ClosureHistoryUnavailable $exception) {
                return ['depth' => null, 'cadence' => null, 'closure' => null,
                    'committed_cardinal_position' => null, 'committed_cardinal_position_direction' => null,
                    'lateral_committal_position' => null, 'lateral_committal_position_direction' => null,
                    'depth_direction' => null, 'cadence_direction' => null, 'admissible' => false,
                    'cadence_interval' => null, 'history_proposition_id' => $phase->cycle->entry_committal_id,
                    'history_source' => 'committed-ancestor', 'unavailable_reason' => 'closure_birth_history_unavailable'];
            }
        };

        return $context === null ? $evaluate() : $context->remember('closure:'.$phase->id.':'.$gate->id, $evaluate);
    }

    public function forCommitment(Proposition $target): array
    {
        return $this->births->stored($target);
    }

    /** The creating Cycle's entry Committal is the sole committed directional predecessor. */
    public function historyFor(Proposition $position, ?ParticipationContext $context = null, ?ContextHorizon $horizon = null): ?CommitmentSnapshot
    {
        return $this->lineage->ancestors($position, $context)->first();
    }
}
