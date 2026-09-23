<?php

namespace App\Services;

use App\Models\Phase;
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
        $calculate = fn () => $this->calculate(collect([$horizon]), $context);

        return $context === null ? $calculate() : $context->remember('exhausted-continuations:'.$horizon, $calculate);
    }

    /** Complete foreign Horizon data, batched without importing it into participant context. */
    public function idsForHorizons(Collection $horizons): Collection
    {
        return $this->calculate($horizons->unique()->values());
    }

    private function calculate(Collection $horizons, ?ParticipationContext $context = null): Collection
    {
        if ($horizons->isEmpty()) {
            return collect();
        }
        $seeds = DB::table('commit_region_exhaustions as exhaustion')
            ->join('propositions as seed', 'seed.id', '=', 'exhaustion.governing_committal_id')
            ->join('phases as source_phase', 'source_phase.id', '=', 'seed.phase_id')
            ->join('cycles as source_cycle', 'source_cycle.id', '=', 'source_phase.cycle_id')
            ->whereIn('source_cycle.context_horizon_id', $horizons)
            ->get(['exhaustion.governing_committal_id', 'source_cycle.context_horizon_id'])
            ->groupBy('context_horizon_id');
        if ($seeds->isEmpty()) {
            return collect();
        }
        $owners = $context !== null ? $context->propositions
            ->filter(fn ($node) => $node->tier === 'expressive' && $seeds->has($node->phase->cycle->context_horizon_id))
            ->map(fn ($node) => $node->phase->cycle->context_horizon_id)
            : DB::table('propositions as node')->join('phases as phase', 'phase.id', '=', 'node.phase_id')
                ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->where('node.tier', 'expressive')->whereIn('cycle.context_horizon_id', $seeds->keys())
                ->pluck('cycle.context_horizon_id', 'node.id');
        $edges = $context !== null ? $context->edges->whereIn('source_proposition_id', $owners->keys())
            : DB::table('supersessions')->whereIn('source_proposition_id', $owners->keys())->get();
        $byHorizon = $edges->groupBy(fn ($edge) => $owners[$edge->source_proposition_id]);
        $failed = app(ExplorationEpochService::class)->currentFailuresForHorizons($seeds->keys(), $owners->keys(), $context);
        $result = collect();
        foreach ($seeds as $horizon => $rows) {
            // Closure stays independent per authoritative creating Horizon. A
            // foreign causal edge must not transport another Horizon's failure.
            $ids = $owners->filter(fn ($owner) => $owner === $horizon)->keys();
            $closed = $rows->pluck('governing_committal_id')->flip()->map(fn () => true);
            $localEdges = $byHorizon->get($horizon, collect());
            $children = $localEdges->groupBy('source_proposition_id');
            $parents = $localEdges->groupBy('target_proposition_id');
            $localFailed = $failed->only($ids->all());
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
                        || $localFailed->has($child->target_proposition_id))) {
                        $closed->put($source, true);
                        $queue[] = $source;
                    }
                }
            }
            $result = $result->union($closed->only($ids->all()));
        }

        return $result;
    }
}
