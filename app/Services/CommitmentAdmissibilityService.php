<?php

namespace App\Services;

use App\Enums\PhaseKind;
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
        private CausalWriteService $writes,
    ) {}

    /** @return array<string, mixed> */
    public function evaluate(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): array
    {
        $phasePosition = $phase->kind === PhaseKind::Order
            && $this->positions->matches($gate, $phase, PhaseKind::Bias);
        $causal = $phasePosition && $this->writes->forPhase($phase, $context)->contains('id', $gate->id);
        $structural = $causal && $this->regions->isCandidateFor($gate, $phase, $context);
        $grounding = $this->grounding($gate, $context);
        $closure = $this->closure->preview($gate, $phase, $context);
        $opportunity = $this->generation->available($phase, 'proposition.commit', $context, $gate);

        return [
            ...$grounding,
            'admissible' => $structural && $grounding['admissible'] && $closure['admissible'] && $opportunity,
            'requirements' => [...$grounding['requirements'], 'phase_position' => $phasePosition, 'causal_write' => $causal, 'active_region_candidate' => $structural, 'closure' => $closure['admissible'], 'write_opportunity' => $opportunity],
            'closure_enforced' => true,
            'closure' => $closure,
            'reasons' => [...($phasePosition ? [] : ['invalid_order_phase_position']), ...$grounding['reasons'], ...($closure['admissible'] ? [] : [$closure['unavailable_reason'] ?? 'depth_cadence_directions_diverge']),
                ...($phasePosition && ! $causal ? ['not_a_causal_write'] : []),
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
