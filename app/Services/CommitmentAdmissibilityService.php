<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;

final readonly class CommitmentAdmissibilityService
{
    public function __construct(
        private ClosureGateService $closure,
        private PropositionStateService $state,
        private ReinforcementService $reinforcement,
        private PhaseGenerationService $generation,
    ) {}

    /** @return array<string, mixed> */
    public function evaluate(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): array
    {
        $grounding = $this->grounding($gate, $context);
        $closure = $this->closure->preview($gate, $phase, $context);
        $opportunity = $this->generation->available($phase, 'proposition.commit', $context, $gate);

        return [
            ...$grounding,
            'admissible' => $grounding['admissible'] && $closure['admissible'] && $opportunity,
            'requirements' => [...$grounding['requirements'], 'closure' => $closure['admissible'], 'write_opportunity' => $opportunity],
            'closure_enforced' => true,
            'closure' => $closure,
            'reasons' => [...$grounding['reasons'], ...($closure['admissible'] ? [] : ['depth_cadence_directions_diverge']),
                ...($opportunity ? [] : ['already_authored_commit_in_row'])],
        ];
    }

    /** @return array<string, mixed> */
    public function grounding(Proposition $gate, ?ParticipationContext $context = null): array
    {
        $attestations = $this->state->attestations(collect([$gate]), $context);
        $hasReceipt = $this->state->receipts($gate, $context)->isNotEmpty();
        $count = $this->reinforcement->count($gate, $context);
        $hasAttestation = $attestations->isNotEmpty();

        return [
            'admissible' => $hasAttestation && $hasReceipt && $count > 0,
            'reinforcement_count' => $count,
            'requirements' => [
                'attestation' => $hasAttestation,
                'receipt' => $hasReceipt,
                'reinforcement' => $count > 0,
                'closure' => null,
            ],
            'closure_enforced' => false,
            'reasons' => array_values(array_filter([
                $hasAttestation ? null : 'missing_effective_attestation',
                $hasReceipt ? null : 'missing_completed_receipt',
                $count > 0 ? null : 'missing_counted_reinforcement',
            ])),
        ];
    }
}
