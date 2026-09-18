<?php

namespace App\Services;

use App\Enums\RestReason;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;

/** Called only after the authoritative action schema has established that Rest is admissible. */
final class RestReasonService
{
    public function derive(Phase $phase, ?ParticipationContext $context = null): RestReason
    {
        if ($phase->cycle->rested_at !== null) {
            return $phase->cycle->rest_reason ?? RestReason::NoAvailableMove;
        }
        $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $phase->gate;
        if ($gate === null || app(ExhaustionService::class)->unavailable($gate)
            || $phase->cycle->contextHorizon->exhausted_at !== null) {
            return RestReason::NoAvailableMove;
        }
        $state = app(PropositionStateService::class);
        // Unobserved structure has not failed a measured probe. A missing vote
        // or evidence can leave a Phase actionless while earlier work remains.
        $writes = app(PropositionOptionService::class)->writeOptionsFor($phase, $context);
        $spent = app(ExplorationEpochService::class)->failuresFor($phase, $writes->pluck('id'), $context);
        $testedWrites = $writes->isNotEmpty() && $writes->pluck('id')->diff($spent->keys())->isEmpty();
        if (! $testedWrites && ($state->attestations(collect([$gate]), $context)->isEmpty()
            || ($state->receipts($gate, $context)->isEmpty()
                && app(HorizonReceiptService::class)->available($phase, $context)->isEmpty()))) {
            return RestReason::NoAvailableMove;
        }

        return app(PotentialChargeService::class)->hasRemainingInHorizon($phase)
            ? RestReason::NoAvailableMove : RestReason::NoRemainingPotential;
    }
}
