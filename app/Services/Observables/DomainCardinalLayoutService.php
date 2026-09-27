<?php

namespace App\Services\Observables;

use App\Domain\Observables\CardinalLayout;
use App\Domain\Observables\CardinalPosition;
use App\Models\ContextHorizon;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only cardinal projection of one Context Horizon.
 *
 * Coordinates belong to the Domain morphology, not to a measurement ancestry
 * walk. The Horizon opener is depth 0 / lateral 1. Each following row preserves
 * the previous row's left-to-right parent order; each parent's children are
 * appended in immutable ledger order and shared children are emitted once.
 *
 * Real Supersessions remain the only topology. A producing Phase Gate is used
 * only as a placement relation when a locally-owned result has no local causal
 * parent (for example a foreign-source Bias result).
 */
final class DomainCardinalLayoutService
{
    public function for(ContextHorizon $horizon, ?ParticipationContext $context = null, ?Proposition $synthetic = null): CardinalLayout
    {
        $key = 'cardinal-layout:'.$horizon->id.':'.($synthetic?->id ?? 'persisted');

        if ($context !== null) {
            return $context->remember($key, fn () => $this->build($horizon, $context, $synthetic));
        }

        return $this->build($horizon, null, $synthetic);
    }

    private function build(ContextHorizon $horizon, ?ParticipationContext $context, ?Proposition $synthetic): CardinalLayout
    {
        $entry = $this->entry($horizon, $context);
        $memberIds = $context !== null
            ? $context->horizonMemberIds($horizon->id)
            : DB::table('context_horizon_memberships')->where('context_horizon_id', $horizon->id)->pluck('proposition_id');

        $ids = $memberIds->push($entry->id)->unique()->values();
        $nodes = $context !== null
            ? $context->nodes($ids)->keyBy('id')
            : Proposition::query()->whereIn('id', $ids)->with(['phase', 'ledgerEntry'])->get()->keyBy('id');

        if (! $nodes->has($entry->id)) {
            $entry->loadMissing(['phase', 'ledgerEntry']);
            $nodes->put($entry->id, $entry);
        }

        if ($synthetic !== null) {
            $nodes->put($synthetic->id, $synthetic);
        }

        $realEdges = $context !== null
            ? $context->edges->whereIn('source_proposition_id', $nodes->keys())
                ->whereIn('target_proposition_id', $memberIds->when($synthetic !== null, fn ($ids) => $ids->push($synthetic->id)))->values()
            : Supersession::query()->whereIn('source_proposition_id', $nodes->keys())
                ->whereIn('target_proposition_id', $memberIds)->with('ledgerEntry')->get();

        if ($synthetic !== null) {
            foreach ($synthetic->parents as $parent) {
                if (! $nodes->has($parent->id)) {
                    continue;
                }
                $edge = new Supersession;
                $edge->forceFill([
                    'source_proposition_id' => $parent->id,
                    'target_proposition_id' => $synthetic->id,
                    'type' => 'commit',
                ]);
                $realEdges->push($edge);
            }
        }

        $localParents = $realEdges->groupBy('target_proposition_id');
        $placementChildren = [];

        foreach ($realEdges as $edge) {
            $placementChildren[$edge->source_proposition_id][] = [
                'id' => $edge->target_proposition_id,
                'sequence' => $this->nodeSequence($nodes[$edge->target_proposition_id] ?? null),
            ];
        }

        // A locally-owned proposition can be causally sourced only from a foreign
        // branch. Its producing Gate still determines where it was written in the
        // local Domain morphology; this relation is never persisted or exposed as
        // causal topology.
        foreach ($nodes as $node) {
            if ($node->id === $entry->id || ! $memberIds->contains($node->id) && $synthetic?->id !== $node->id) {
                continue;
            }
            $hasLocalParent = ($localParents->get($node->id, collect()))
                ->contains(fn ($edge) => $nodes->has($edge->source_proposition_id));
            if ($hasLocalParent) {
                continue;
            }

            $gateId = $node->phase?->gate_proposition_id;
            if ($gateId === null && $node->exists) {
                $gateId = $node->phase()->value('gate_proposition_id');
            }
            if ($gateId === null) {
                $gateId = $node->phase?->cycle?->entry_committal_id;
            }
            if ($gateId !== null && $nodes->has($gateId)) {
                $placementChildren[$gateId][] = [
                    'id' => $node->id,
                    'sequence' => $this->nodeSequence($node),
                ];
            }
        }

        foreach ($placementChildren as &$children) {
            usort($children, fn ($a, $b) => [$a['sequence'], $a['id']] <=> [$b['sequence'], $b['id']]);
        }
        unset($children);

        $positions = [$entry->id => new CardinalPosition($horizon->id, 0, 1)];
        $row = [$entry->id];
        $depth = 0;

        while ($row !== []) {
            $next = [];
            $queued = [];
            foreach ($row as $parentId) {
                foreach ($placementChildren[$parentId] ?? [] as $child) {
                    $id = $child['id'];
                    if (isset($positions[$id]) || isset($queued[$id])) {
                        continue;
                    }
                    $queued[$id] = true;
                    $next[] = $id;
                }
            }
            if ($next === []) {
                break;
            }
            $depth++;
            foreach ($next as $index => $id) {
                $positions[$id] = new CardinalPosition($horizon->id, $depth, $index + 1);
            }
            $row = $next;
        }

        $unplaced = $memberIds->reject(fn ($id) => isset($positions[$id]));
        if ($synthetic !== null && ! isset($positions[$synthetic->id])) {
            $unplaced->push($synthetic->id);
        }
        if ($unplaced->isNotEmpty()) {
            throw new DomainException('Every Domain proposition requires a deterministic cardinal position: '.$unplaced->implode(', '));
        }

        return new CardinalLayout($horizon, $entry->id, $nodes, $realEdges, $positions);
    }

    private function entry(ContextHorizon $horizon, ?ParticipationContext $context): Proposition
    {
        if ($horizon->opened_by_proposition_id !== null) {
            $entry = $context?->propositions->get($horizon->opened_by_proposition_id)
                ?? Proposition::with(['phase', 'ledgerEntry'])->find($horizon->opened_by_proposition_id);
            if ($entry === null) {
                throw new DomainException('A non-root Horizon requires its opening Committal.');
            }

            return $entry;
        }

        $memberIds = $context !== null ? $context->horizonMemberIds($horizon->id)
            : DB::table('context_horizon_memberships')->where('context_horizon_id', $horizon->id)->pluck('proposition_id');
        $query = Proposition::query()->whereIn('id', $memberIds)->where('tier', 'committal')
            ->with(['phase', 'ledgerEntry']);
        $entry = $query->get()->sortBy(fn ($node) => $this->nodeSequence($node))->first();
        if ($entry === null) {
            throw new DomainException('The Origin Horizon requires a governing Committal.');
        }

        return $entry;
    }

    private function nodeSequence(?Proposition $node): int
    {
        if ($node === null) {
            return PHP_INT_MAX;
        }
        if (! $node->relationLoaded('ledgerEntry')) {
            $node->load('ledgerEntry');
        }

        return (int) ($node->ledgerEntry?->sequence ?? PHP_INT_MAX);
    }
}
