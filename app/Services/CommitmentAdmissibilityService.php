<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use App\Services\Observables\ReinforcementService;

final readonly class CommitmentAdmissibilityService
{
    public function __construct(
        private ClosureGateService $closure,
        private PropositionStateService $state,
        private ReinforcementService $reinforcement,
    ) {}

    /** @return array<string, mixed> */
    public function evaluate(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): array
    {
        $grounding = $this->grounding($gate, $context);
        $closure = $this->closure->preview($gate, $phase, $context);

        return [
            ...$grounding,
            'admissible' => $grounding['admissible'] && $closure['admissible'],
            'requirements' => [...$grounding['requirements'], 'closure' => $closure['admissible']],
            'closure_enforced' => true,
            'closure' => $closure,
            'reasons' => [...$grounding['reasons'], ...($closure['admissible'] ? [] : ['depth_cadence_directions_diverge'])],
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
