<?php

namespace App\Services\Maintenance;

use App\Models\Proposition;
use App\Services\CommitRegionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Explicit pre-#196 migration compatibility. Never used by live ownership. */
final class LegacyCommitRegionOwnership
{
    public function seedFor(Proposition $position): ?Proposition
    {
        if (Schema::hasColumn('cycles', 'entry_committal_id')) {
            return app(CommitRegionService::class)->seedFor($position);
        }
        if (! $position->exists) {
            return null;
        }
        // Expand only indexed parent edges from the current frontier. PostgreSQL
        // can choose a whole-relation recursive-CTE plan even for a short path.
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $frontier = collect([$position]);
        $seen = $seeds = [];
        while ($frontier->isNotEmpty()) {
            $expressive = [];
            foreach ($frontier as $node) {
                if (isset($seen[$node->id])) {
                    continue;
                }
                $seen[$node->id] = true;
                if ($node->tier === 'committal') {
                    $seeds[$node->id] = $node;
                } elseif ($node->tier === 'expressive') {
                    $expressive[] = $node->id;
                }
            }
            // Different first Commits are ambiguous; never union their regions.
            if (count($seeds) > 1) {
                return null;
            }
            if ($expressive === []) {
                break;
            }
            $parents = DB::table('supersessions')->whereIn('target_proposition_id', $expressive)->select('source_proposition_id');
            $frontier = Proposition::whereIn('id', $parents)->whereNotIn('id', array_keys($seen))
                ->whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $substrateId))->get();
        }

        return count($seeds) === 1 ? reset($seeds) : null;
    }

}
