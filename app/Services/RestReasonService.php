<?php

namespace App\Services;

use App\Enums\RestReason;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

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
        if ($state->attestations(collect([$gate]), $context)->isEmpty()
            || ($state->receipts($gate, $context)->isEmpty()
                && app(HorizonReceiptService::class)->available($phase, $context)->isEmpty())) {
            return RestReason::NoAvailableMove;
        }
        $horizonId = $phase->cycle->context_horizon_id;
        app(PotentialProjectionService::class)->assertCurrent($phase->cycle->participant->substrate_id);
        $local = DB::table('potential_entries')->where('context_horizon_id', $horizonId)
            ->where('raw_potential_charge', '>', 0.00000005)->exists();
        $frontier = json_decode(DB::table('potential_region_states')->where('context_horizon_id', $horizonId)
            ->value('frontier') ?? '[]', true, flags: JSON_THROW_ON_ERROR);

        return $local || collect($frontier)->contains(fn ($entry) => $entry['raw_potential_charge'] > 0.00000005)
            ? RestReason::NoAvailableMove : RestReason::NoRemainingPotential;
    }
}
