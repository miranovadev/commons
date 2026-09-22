<?php

namespace App\Services;

use App\Domain\Observables\ClosureValues;
use App\Enums\ObservableDirection;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\CommittalLineageService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Participation\ParticipationContext;

class ClosureGateService
{
    public function __construct(
        private readonly LineageMeasurementService $measurements,
        private readonly CommittalLineageService $lineage,
    ) {}

    public function preview(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): array
    {
        $evaluate = function () use ($gate, $phase, $context): array {
            // History and interval belong to the producing Cycle, never the Gate's owner.
            $candidate = new Proposition;
            $candidate->forceFill(['tier' => 'committal']);
            $candidate->setRelation('phase', $phase);
            try {
                return $this->evaluate($this->measurements->prospectiveCommit($gate, $phase, $context)->closure(), $candidate, $context);
            } catch (ClosureHistoryUnavailable $exception) {
                return ['depth' => null, 'cadence' => null, 'closure' => null,
                    'depth_direction' => null, 'cadence_direction' => null, 'admissible' => false,
                    'cadence_interval' => $this->lineage->interval($candidate, $context),
                    'history_proposition_id' => $phase->cycle->governing_committal_id,
                    'history_source' => 'committed-ancestor', 'unavailable_reason' => 'cadence_history_unavailable'];
            }
        };
        return $context === null ? $evaluate() : $context->remember('closure:'.$phase->id.':'.$gate->id, $evaluate);
    }

    public function forCommitment(Proposition $target): array
    {
        return $this->evaluate($this->measurements->calculate($target)->closure(), $target);
    }

    /** The creating Cycle's governor is the sole committed directional predecessor. */
    public function historyFor(Proposition $position, ?ParticipationContext $context = null, ?ContextHorizon $horizon = null): ?CommitmentSnapshot
    {
        return $this->lineage->ancestors($position, $context)->first();
    }

    private function evaluate(ClosureValues $closure, Proposition $position, ?ParticipationContext $context = null): array
    {
        $history = $this->historyFor($position, $context);
        $snapshot = $this->lineage->records($position, $context)->get($position->id);

        return [
            ...$closure->resolve(
                $history?->depth_direction ?? ObservableDirection::Negative,
                $history?->cadence_direction ?? ObservableDirection::Negative,
            ),
            'cadence_interval' => $snapshot !== null ? $snapshot->cadence_interval
                : ($history === null ? null : $this->lineage->interval($position, $context)),
            'history_proposition_id' => $history?->proposition_id,
            'history_source' => $history === null ? 'negative-default' : 'committed-ancestor',
        ];
    }
}
