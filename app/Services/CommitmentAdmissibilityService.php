<?php

namespace App\Services;

use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Phase;

final readonly class CommitmentAdmissibilityService
{
    public function __construct(private ClosureGateService $closure) {}

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
        $attestations = $gate->effectiveAttestations()->pluck('id');
        $hasReceipt = Receipt::query()
            ->whereHas('instrumentExecution', fn ($query) => $query->where('status', 'completed'))
            ->where(function ($query) use ($gate, $attestations): void {
                $query->whereMorphedTo('receiptable', $gate)
                    ->orWhere(function ($query) use ($attestations): void {
                        $query->where('receiptable_type', (new \App\Models\Attestation)->getMorphClass())
                            ->whereIn('receiptable_id', $attestations);
                    });
            })
            ->exists();
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
