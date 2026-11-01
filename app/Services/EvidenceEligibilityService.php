<?php

namespace App\Services;

use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;

/** One boundary for evidence prerequisites; participant uptake remains independent. */
final readonly class EvidenceEligibilityService
{
    public function __construct(private PropositionStateService $state) {}

    public function hasReceipt(Proposition $proposition, ?ParticipationContext $context = null): bool
    {
        return $this->state->evidenceReceipts($proposition, $context)->isNotEmpty();
    }
}
