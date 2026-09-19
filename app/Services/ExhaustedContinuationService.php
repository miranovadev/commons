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
            $closed = DB::table('horizon_exhaustions as exhaustion')
                ->join('context_horizons as horizon', 'horizon.id', '=', 'exhaustion.context_horizon_id')
                ->where('horizon.parent_context_horizon_id', $horizon)
                ->pluck('exhaustion.opening_proposition_id')->filter()->flip()->map(fn () => true);
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
