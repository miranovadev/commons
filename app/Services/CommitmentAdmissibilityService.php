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
        private CommitRegionService $regions,
        private PhasePositionService $positions,
    ) {}

    /** @return array<string, mixed> */
    public function evaluate(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): array
    {
        $phasePosition = $phase->kind === \App\Enums\PhaseKind::Order
            && $this->positions->matches($gate, $phase, \App\Enums\PhaseKind::Bias);
        $structural = $phasePosition && $this->regions->isCandidateFor($gate, $phase, $context);
        $grounding = $this->grounding($gate, $context);
        $closure = $this->closure->preview($gate, $phase, $context);
        $opportunity = $this->generation->available($phase, 'proposition.commit', $context, $gate);

        return [
            ...$grounding,
            'admissible' => $structural && $grounding['admissible'] && $closure['admissible'] && $opportunity,
            'requirements' => [...$grounding['requirements'], 'phase_position' => $phasePosition, 'active_region_candidate' => $structural, 'closure' => $closure['admissible'], 'write_opportunity' => $opportunity],
            'closure_enforced' => true,
            'closure' => $closure,
            'reasons' => [...($phasePosition ? [] : ['invalid_order_phase_position']), ...$grounding['reasons'], ...($closure['admissible'] ? [] : ['depth_cadence_directions_diverge']),
                ...($phasePosition && ! $opportunity ? ['source_already_committed'] : []),
                ...($phasePosition && $opportunity && ! $structural ? ['invalid_commit_region_candidate'] : [])],
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
            'admissible' => $hasAttestation && $hasReceipt,
            'reinforcement_count' => $count,
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
