<?php

namespace App\Services;

use App\Models\ContextHorizon;
use Closure;
use DomainException;
use Illuminate\Support\Collection;

/** Horizon scope restricts an already backward-only participant lineage. */
final class RecoveryBasinResolver
{
    public function resolve(ContextHorizon $start, Collection $horizons, Closure $candidatesFor,
        Closure $canCross, ?Closure $isClosed = null): array
    {
        $isClosed ??= fn (ContextHorizon $basin): bool => $basin->exhausted_at !== null;
        $horizons = $horizons->where('substrate_id', $start->substrate_id)->keyBy('id');
        $ancestors = [];
        $basin = $horizons->get($start->id)
            ?? throw new DomainException('Recovery requires the starting Context Horizon.');
        while ($basin !== null) {
            if (isset($ancestors[$basin->id])) {
                throw new DomainException('Recovery requires acyclic Context Horizon provenance.');
            }
            $ancestors[$basin->id] = $basin;
            $basin = $basin->parent_context_horizon_id === null ? null : ($horizons->get($basin->parent_context_horizon_id)
                ?? throw new DomainException('Recovery requires the immediate provenance Context Horizon.'));
        }
        $searched = [];
        $depth = 0;
        foreach ($ancestors as $basin) {
            $candidates = $candidatesFor($basin);
            $searched[] = ['context_horizon_id' => $basin->id, 'domain' => $basin->domain->value,
                'committal_proposition_id' => $basin->opened_by_proposition_id,
                'eligible_candidates' => $candidates->count()];
            if ($candidates->isNotEmpty() || $basin->parent_context_horizon_id === null
                || ! $isClosed($basin) || ! $canCross($basin)) {
                break;
            }
            $depth++;
        }

        return ['candidates' => $candidates, 'searched_basins' => $searched, 'fallback_depth' => $depth];
    }
}
