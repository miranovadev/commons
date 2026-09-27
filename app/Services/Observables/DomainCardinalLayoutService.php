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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only cardinal projection of a complete Context Horizon.
 *
 * Row/depth is canonical producing Phase position. Lateral position is the
 * one-based ordinal in that complete row. Placement is independent of causal
 * topology; genuine Supersessions are retained separately for Shape Topology.
 */
final class DomainCardinalLayoutService
{
    public function for(ContextHorizon $horizon, ?ParticipationContext $context = null, ?Proposition $synthetic = null): CardinalLayout
    {
        if ($context !== null && $synthetic === null) {
            /** @var Collection<string, CardinalLayout> $layouts */
            $layouts = $context->remember('cardinal-layouts:authoritative', function () use ($context, $horizon) {
                return $this->buildBatch(
                    $context->horizons->values()->push($horizon)->unique('id')->values()
                );
            });

            if ($layout = $layouts->get($horizon->id)) {
                return $layout;
            }
        }

        return $this->build($horizon, $synthetic);
    }

    /** @param Collection<int, ContextHorizon> $horizons
     *  @return Collection<string, CardinalLayout>
     */
    private function buildBatch(Collection $horizons): Collection
    {
        $horizons = $horizons->unique('id')->keyBy('id');
        $memberships = DB::table('context_horizon_memberships')
            ->whereIn('context_horizon_id', $horizons->keys())
            ->get(['context_horizon_id', 'proposition_id']);
        $memberIdsByHorizon = $memberships->groupBy('context_horizon_id')
            ->map(fn ($rows) => $rows->pluck('proposition_id')->values());

        $allIds = $memberships->pluck('proposition_id')
            ->merge($horizons->pluck('opened_by_proposition_id')->filter())
            ->unique()->values();
        $nodes = Proposition::query()->whereIn('id', $allIds)
            ->with(['phase.cycle', 'ledgerEntry'])->get()->keyBy('id');
        $edges = Supersession::query()
            ->whereIn('source_proposition_id', $allIds)
            ->whereIn('target_proposition_id', $memberships->pluck('proposition_id'))
            ->with('ledgerEntry')->get();

        return $horizons->mapWithKeys(function (ContextHorizon $horizon) use ($memberIdsByHorizon, $nodes, $edges) {
            $memberIds = $memberIdsByHorizon->get($horizon->id, collect());
            $entry = $this->entryFrom($horizon, $memberIds, $nodes);
            $ids = $memberIds->push($entry->id)->unique();
            $domainNodes = $nodes->only($ids)->keyBy('id');
            if (! $domainNodes->has($entry->id)) {
                $domainNodes->put($entry->id, $entry);
            }
            $domainEdges = $edges
                ->whereIn('source_proposition_id', $domainNodes->keys())
                ->whereIn('target_proposition_id', $memberIds)->values();

            return [$horizon->id => $this->assemble($horizon, $entry, $domainNodes, $domainEdges)];
        });
    }

    private function build(ContextHorizon $horizon, ?Proposition $synthetic): CardinalLayout
    {
        $layout = $this->buildBatch(collect([$horizon]))->get($horizon->id);
        if ($layout === null || $synthetic === null) {
            if ($layout === null) {
                throw new DomainException('A Context Horizon requires a cardinal layout.');
            }

            return $layout;
        }

        $nodes = $layout->propositions->put($synthetic->id, $synthetic);
        $edges = clone $layout->supersessions;
        foreach ($synthetic->parents as $parent) {
            $edge = new Supersession;
            $edge->forceFill([
                'source_proposition_id' => $parent->id,
                'target_proposition_id' => $synthetic->id,
                'type' => 'commit',
            ]);
            $edges->push($edge);
        }

        $entry = $nodes->get($layout->entryId);

        return $this->assemble($horizon, $entry, $nodes, $edges);
    }

    private function assemble(
        ContextHorizon $horizon,
        Proposition $entry,
        Collection $nodes,
        Collection $realEdges,
    ): CardinalLayout {
        $positions = [$entry->id => new CardinalPosition($horizon->id, 0, 1)];
        $rows = [];
        foreach ($nodes as $node) {
            if ($node->id === $entry->id) {
                continue;
            }
            $rows[$this->canonicalDepth($node)][] = $node;
        }
        ksort($rows);

        foreach ($rows as $depth => $row) {
            usort($row, function (Proposition $a, Proposition $b) use ($positions, $entry): int {
                $parentA = $this->placementParentId($a, $entry);
                $parentB = $this->placementParentId($b, $entry);
                $parentOrderA = $positions[$parentA]->lateral ?? PHP_INT_MAX;
                $parentOrderB = $positions[$parentB]->lateral ?? PHP_INT_MAX;

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

        return $kind instanceof PhaseKind ? $kind->value : (int) $kind;
    }

    /**
     * Producing Phase Gate is a placement relation only. For Distinguish, the
     * Cycle entry Committal is the placement parent. Neither relation creates a
     * Supersession or changes causal/movement authority.
     */
    private function placementParentId(Proposition $node, Proposition $entry): string
    {
        return $node->phase?->gate_proposition_id
            ?? $node->phase?->cycle?->entry_committal_id
            ?? $entry->id;
    }

    private function entryFrom(ContextHorizon $horizon, Collection $memberIds, Collection $nodes): Proposition
    {
        if ($horizon->opened_by_proposition_id !== null) {
            $entry = $nodes->get($horizon->opened_by_proposition_id);
            if ($entry === null) {
                throw new DomainException('A non-root Horizon requires its opening Committal.');
            }

            return $entry;
        }

        $entry = $nodes->only($memberIds)->filter(fn ($node) => $node->tier === 'committal')
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
