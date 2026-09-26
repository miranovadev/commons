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

    public function canAttest(Proposition $proposition, ?ParticipationContext $context = null): bool
    {
        // Deliberately existence-only. A future read policy belongs here, not in
        // duplicated UI/schema/dispatcher conditions. No truth judgment is implied.
        return $this->hasReceipt($proposition, $context);
    }
}
