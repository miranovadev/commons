<?php

namespace App\Services;

use App\Models\ContextHorizon;
use Closure;
use DomainException;
use Illuminate\Support\Collection;

/** Derive a recovery frontier from causal Horizons, without a global active marker. */
final class RecoveryBasinResolver
{
    /**
     * A viable descendant shadows its ancestors. Sibling branches remain peers;
     * the caller ranks their viable frontier candidates using the existing field.
     * Only exhaustion of this subtree permits retreat through its own parent.
     *
     * @param  Collection<string, ContextHorizon>  $horizons
     * @param  Closure(ContextHorizon): Collection  $candidatesFor
     * @param  null|Closure(ContextHorizon): bool  $isClosed
     * @return array{candidates: Collection, searched_basins: array, fallback_depth: int}
     */
    public function resolve(ContextHorizon $start, Collection $horizons, Closure $candidatesFor, ?Closure $isClosed = null): array
    {
        $isClosed ??= fn (ContextHorizon $basin): bool => $basin->exhausted_at !== null;
        $horizons = $horizons->where('substrate_id', $start->substrate_id)->keyBy('id');
        $children = $horizons->groupBy('parent_context_horizon_id');
        $visited = $searching = $searched = [];
        $visit = function (ContextHorizon $basin) use (&$visit, &$visited, &$searching, &$searched, $children, $candidatesFor): Collection {
            if (isset($searching[$basin->id])) {
                throw new DomainException('Recovery requires acyclic Context Horizon provenance.');
            }
            if (isset($visited[$basin->id])) {
                return collect();
            }
            $searching[$basin->id] = true;
            $candidates = collect();
            foreach ($children->get($basin->id, collect())->sortBy('id') as $child) {
                $candidates = $candidates->concat($visit($child));
            }
            if ($candidates->isEmpty()) {
                $candidates = $candidatesFor($basin);
                $searched[] = [
                    'context_horizon_id' => $basin->id,
                    'domain' => $basin->domain->value,
                    'eligible_candidates' => $candidates->count(),
                ];
            }
            unset($searching[$basin->id]);
            $visited[$basin->id] = true;

            return $candidates;
        };

        $basin = $horizons->get($start->id)
            ?? throw new DomainException('Recovery requires the starting Context Horizon.');
        $fallbackDepth = 0;
        $ancestors = [];
        while (true) {
            if (isset($ancestors[$basin->id])) {
                throw new DomainException('Recovery requires acyclic Context Horizon provenance.');
            }
            $ancestors[$basin->id] = true;
            $candidates = $visit($basin);
            if ($candidates->isNotEmpty() || $basin->parent_context_horizon_id === null || ! $isClosed($basin)) {
                return ['candidates' => $candidates, 'searched_basins' => $searched, 'fallback_depth' => $fallbackDepth];
            }
            $basin = $horizons->get($basin->parent_context_horizon_id)
                ?? throw new DomainException('Recovery requires the immediate provenance Context Horizon.');
            $fallbackDepth++;
        }
    }
}
