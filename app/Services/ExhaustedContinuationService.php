<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Successful history can terminate in an exhausted Horizon without becoming a failure. */
final class ExhaustedContinuationService
{
    /** @return Collection<string, bool> */
    public function ids(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $horizon = $phase->cycle->context_horizon_id;
        $calculate = function () use ($horizon, $context): Collection {
            $closed = DB::table('commit_region_exhaustions as exhaustion')
                ->join('propositions as seed', 'seed.id', '=', 'exhaustion.governing_committal_id')
                ->join('phases as source_phase', 'source_phase.id', '=', 'seed.phase_id')
                ->join('cycles as source_cycle', 'source_cycle.id', '=', 'source_phase.cycle_id')
                ->where('source_cycle.context_horizon_id', $horizon)
                ->pluck('exhaustion.governing_committal_id')->flip()->map(fn () => true);
            if ($closed->isEmpty()) {
                return collect();
            }
            $ids = $context !== null ? $context->propositions
                ->filter(fn ($node) => $node->tier === 'expressive' && $node->phase->cycle->context_horizon_id === $horizon)->keys()
                : Proposition::where('tier', 'expressive')->whereHas('phase.cycle', fn ($query) => $query->where('context_horizon_id', $horizon))->pluck('id');
            $edges = $context !== null ? $context->edges->whereIn('source_proposition_id', $ids)
                : DB::table('supersessions')->whereIn('source_proposition_id', $ids)->get();
            $children = $edges->groupBy('source_proposition_id');
            $parents = $edges->groupBy('target_proposition_id');
            $failed = app(ExplorationEpochService::class)->currentFailures($horizon, $ids, $context);
            $queue = $closed->keys()->all();
            for ($i = 0; $i < count($queue); $i++) {
                foreach ($parents->get($queue[$i], collect()) as $edge) {
                    $source = $edge->source_proposition_id;
                    if ($closed->has($source)) {
                        continue;
                    }
                    // At least one child reaches explicit Horizon Exhaustion.
                    // Other live continuations keep a shared source actionable.
                    if ($children[$source]->every(fn ($child) => $closed->has($child->target_proposition_id)
                        || $failed->has($child->target_proposition_id))) {
                        $closed->put($source, true);
                        $queue[] = $source;
                    }
                }
            }

            return $closed->only($ids->all());
        };

        return $context === null ? $calculate() : $context->remember('exhausted-continuations:'.$horizon, $calculate);
    }
}
