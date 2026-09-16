<?php

namespace App\Services;

use App\Jobs\RefreshPotentialProjection;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Substrate;
use Illuminate\Support\Facades\DB;

/** Indexed invalidation only. Calculation never runs in the contribution transaction. */
final class FieldMutationService
{
    public function touch(Phase $phase, array $propositionIds = []): void
    {
        $this->mark($phase->cycle->participant->substrate_id,
            [$phase->cycle->context_horizon_id], array_filter($propositionIds));
    }

    public function mark(string $substrateId, array $horizonIds = [], array $propositionIds = []): void
    {
        Substrate::whereKey($substrateId)->increment('field_version');
        $dependencies = DB::table('potential_region_dependencies')->whereIn('proposition_id', $propositionIds)
            ->pluck('context_horizon_id');
        // A boundary's own snapshot is immutable, but its Phase touches/evidence
        // can change the comparison used by the Domain it opens.
        $opened = ContextHorizon::whereIn('opened_by_proposition_id', $propositionIds)->pluck('id');
        $ids = collect($horizonIds)->merge($dependencies)->merge($opened)->filter()->unique();
        foreach ($ids as $id) {
            DB::table('potential_dirty_horizons')->insertOrIgnore([
                'substrate_id' => $substrateId, 'context_horizon_id' => $id,
            ]);
        }
        // Durable dirt survives process death; the scheduled sweep is the retry
        // boundary. Do not dispatch a synchronous queue job while locks are held.
    }
}
