<?php

namespace App\Services\Observables;

use App\Domain\Observables\CardinalLayout;
use App\Domain\Observables\CardinalPosition;
use App\Enums\PhaseKind;
use App\Models\ContextHorizon;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Read-only cardinal projection of one complete Context Horizon.
 *
 * Row/depth is canonical producing Phase position, not causal-path length.
 * Lateral position is the one-based ordinal in that complete row. Rows preserve
 * the previous row's parent order; siblings of the same placement parent use
 * immutable ledger creation order. Placement is independent of causal topology.
 */
final class DomainCardinalLayoutService
{
    public function for(ContextHorizon $horizon, ?ParticipationContext $context = null, ?Proposition $synthetic = null): CardinalLayout
    {
        $key = 'cardinal-layout:'.$horizon->id.':'.($synthetic?->id ?? 'persisted');

        return $context !== null
            ? $context->remember($key, fn () => $this->build($horizon, $synthetic))
            : $this->build($horizon, $synthetic);
    }

    private function build(ContextHorizon $horizon, ?Proposition $synthetic): CardinalLayout
    {
        $entry = $this->entry($horizon);
        $memberIds = DB::table('context_horizon_memberships')
            ->where('context_horizon_id', $horizon->id)->pluck('proposition_id');

        $ids = $memberIds->push($entry->id)->unique()->values();
        $nodes = Proposition::query()->whereIn('id', $ids)
            ->with(['phase.cycle', 'ledgerEntry'])->get()->keyBy('id');
        if (! $nodes->has($entry->id)) {
            $entry->loadMissing(['phase.cycle', 'ledgerEntry']);
            $nodes->put($entry->id, $entry);
        }
        if ($synthetic !== null) {
            $nodes->put($synthetic->id, $synthetic);
        }

        // Real edges are retained solely for Shape Topology. They do not assign
        // cardinal rows or columns.
        $realEdges = Supersession::query()
            ->whereIn('source_proposition_id', $nodes->keys())
            ->whereIn('target_proposition_id', $memberIds)
            ->with('ledgerEntry')->get();
        if ($synthetic !== null) {
            foreach ($synthetic->parents as $parent) {
                $edge = new Supersession;
                $edge->forceFill([
                    'source_proposition_id' => $parent->id,
                    'target_proposition_id' => $synthetic->id,
                    'type' => 'commit',
                ]);
                $realEdges->push($edge);
            }
        }

        $positions = [$entry->id => new CardinalPosition($horizon->id, 0, 1)];
        $rows = [];
        foreach ($nodes as $node) {
            if ($node->id === $entry->id) {
                continue;
            }
            $depth = $this->canonicalDepth($node);
            $rows[$depth][] = $node;
        }
        ksort($rows);

        foreach ($rows as $depth => $row) {
            usort($row, function (Proposition $a, Proposition $b) use ($positions, $entry): int {
                $parentA = $this->placementParentId($a, $entry);
                $parentB = $this->placementParentId($b, $entry);
                $positionA = $positions[$parentA] ?? null;
                $positionB = $positions[$parentB] ?? null;

                // A parent already placed in the preceding structural rows owns
                // the primary left-to-right ordering. Fixtures or bootstrap rows
                // without such a parent remain deterministic by creation order.
                $parentOrderA = $positionA?->lateral ?? PHP_INT_MAX;
                $parentOrderB = $positionB?->lateral ?? PHP_INT_MAX;

                return [$parentOrderA, $this->nodeSequence($a), $a->id]
                    <=> [$parentOrderB, $this->nodeSequence($b), $b->id];
            });

            foreach ($row as $index => $node) {
                $positions[$node->id] = new CardinalPosition($horizon->id, (int) $depth, $index + 1);
            }
        }

        return new CardinalLayout($horizon, $entry->id, $nodes, $realEdges, $positions);
    }

    private function canonicalDepth(Proposition $node): int
    {
        $kind = $node->phase?->kind;
        if ($kind instanceof PhaseKind) {
            return $kind->value;
        }

        return (int) $kind;
    }

    /**
     * Producing Phase Gate is a placement relation only. For Distinguish, the
     * Cycle entry Committal is the placement parent. Neither relation creates a
     * Supersession or changes causal/movement authority.
     */
    private function placementParentId(Proposition $node, Proposition $entry): string
    {
        $gate = $node->phase?->gate_proposition_id;
        if ($gate !== null) {
            return $gate;
        }

        return $node->phase?->cycle?->entry_committal_id ?? $entry->id;
    }

    private function entry(ContextHorizon $horizon): Proposition
    {
        if ($horizon->opened_by_proposition_id !== null) {
            $entry = Proposition::with(['phase.cycle', 'ledgerEntry'])->find($horizon->opened_by_proposition_id);
            if ($entry === null) {
                throw new DomainException('A non-root Horizon requires its opening Committal.');
            }

            return $entry;
        }

        $memberIds = DB::table('context_horizon_memberships')
            ->where('context_horizon_id', $horizon->id)->pluck('proposition_id');
        $entry = Proposition::query()->whereIn('id', $memberIds)->where('tier', 'committal')
            ->with(['phase.cycle', 'ledgerEntry'])->get()
            ->sortBy(fn ($node) => $this->nodeSequence($node))->first();
        if ($entry === null) {
            throw new DomainException('The Origin Horizon requires a governing Committal.');
        }

        return $entry;
    }

    private function nodeSequence(Proposition $node): int
    {
        if (! $node->relationLoaded('ledgerEntry')) {
            $node->load('ledgerEntry');
        }

        return (int) ($node->ledgerEntry?->sequence ?? PHP_INT_MAX);
    }
}
