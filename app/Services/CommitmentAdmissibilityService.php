<?php

namespace App\Services;

use App\Models\Proposition;
use App\Models\Receipt;

final readonly class CommitmentAdmissibilityService
{
    /** @return array{admissible: bool, requirements: array{attestation: bool, receipt: bool, closure: null}, closure_enforced: bool, reasons: list<string>} */
    public function evaluate(Proposition $gate): array
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
