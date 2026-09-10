<?php

namespace App\Services;

use App\Models\Proposition;
use App\Models\Phase;

final readonly class CommitmentAdmissibilityService
{
    public function __construct(
        private ClosureGateService $closure,
        private PropositionStateService $state,
    ) {}

    /** @return array<string, mixed> */
    public function evaluate(Proposition $gate, Phase $phase): array
    {
        $grounding = $this->grounding($gate);
        $closure = $this->closure->preview($gate, $phase);
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
    public function grounding(Proposition $gate): array
    {
        $attestations = $this->state->attestations(collect([$gate]));
        $hasReceipt = $this->state->receipts($gate)->isNotEmpty();
        $hasAttestation = $attestations->isNotEmpty();

        return [
            'admissible' => $hasAttestation && $hasReceipt,
            'requirements' => [
                'attestation' => $hasAttestation,
                'receipt' => $hasReceipt,
                'closure' => null,
            ],
            'closure_enforced' => false,
            'reasons' => array_values(array_filter([
                $hasAttestation ? null : 'missing_effective_attestation',
                $hasReceipt ? null : 'missing_completed_receipt',
            ])),
        ];
    }
}
