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
 * Read-only cardinal projection of a complete Context Horizon.
 *
 * Membership follows Domain ownership. Rows use breadth-first local structure,
 * including explicit non-causal placement for foreign-input results. Genuine
 * Supersessions remain separate from that read-only placement relation.
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
            ->whereIn('target_proposition_id', $memberships->pluck('proposition_id'))
            ->with('ledgerEntry')->get();

        return $horizons->mapWithKeys(function (ContextHorizon $horizon) use ($memberIdsByHorizon, $nodes, $edges) {
            $memberIds = $memberIdsByHorizon->get($horizon->id, collect());
            $entry = $this->entryFrom($horizon, $memberIds, $nodes);
            $ids = $memberIds->push($entry->id)->unique();
            $domainNodes = $nodes->only($ids->all())->keyBy('id');
            if (! $domainNodes->has($entry->id)) {
                $domainNodes->put($entry->id, $entry);
            }
            $domainEdges = $edges
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

        $causalChildren = $realEdges
            ->filter(fn (Supersession $edge) => $nodes->has($edge->source_proposition_id)
                && $nodes->has($edge->target_proposition_id))
            ->groupBy('source_proposition_id')
            ->map(fn (Collection $edges) => $edges->pluck('target_proposition_id'));

        // This is a coordinate relation, never a Supersession. Use it only when
        // the owning Domain has no causal parent for a foreign-input result.
        // Ordinary local causal morphology always supplies its own placement.
        $placementChildren = $this->foreignInputPlacementChildren($horizon, $entry, $nodes, $realEdges);
        $children = $nodes->keys()->mapWithKeys(function (string $id) use ($causalChildren, $placementChildren, $nodes) {
            $ids = $causalChildren->get($id, collect())
                ->merge($placementChildren->get($id, collect()))->unique();

            return [$id => $ids->sort(fn (string $a, string $b) =>
                [$this->nodeSequence($nodes->get($a)), $a]
                    <=> [$this->nodeSequence($nodes->get($b)), $b])->values()];
        });

        $row = [$entry->id];
        $depth = 0;

        while ($row !== []) {
            $next = [];
            $queued = [];

            foreach ($row as $parentId) {
                foreach ($children->get($parentId, collect()) as $childId) {
                    if (isset($positions[$childId]) || isset($queued[$childId])) {
                        continue;
                    }

                    $queued[$childId] = true;
                    $next[] = $childId;
                }
            }

            if ($next === []) {
                break;
            }

            $depth++;
            foreach ($next as $index => $childId) {
                $positions[$childId] = new CardinalPosition($horizon->id, $depth, $index + 1);
            }

            $row = $next;
        }

        $unplaced = $nodes->keys()
            ->reject(fn (string $id) => $id === $entry->id || isset($positions[$id]))
            ->values();

        if ($unplaced->isNotEmpty()) {
            throw new DomainException(
                'Owned Domain propositions require local causal structure or an explicit foreign-input '
                .'placement relation from their producing Phase Gate: '.$unplaced->implode(', ')
            );
        }

        return new CardinalLayout($horizon, $entry->id, $nodes, $realEdges, $positions);
    }

    /** @return Collection<string, Collection<int, string>> */
    private function foreignInputPlacementChildren(
        ContextHorizon $horizon,
        Proposition $entry,
        Collection $nodes,
        Collection $realEdges,
    ): Collection {
        $placements = collect();
        $parents = $realEdges->groupBy('target_proposition_id');
        foreach ($nodes as $node) {
            if ($node->id === $entry->id) {
                continue;
            }
            $inputs = $parents->get($node->id, collect());
            if ($inputs->isEmpty() || $inputs->contains(fn ($edge) => $nodes->has($edge->source_proposition_id))) {
                continue;
            }

            $phase = $node->phase;
            $gateId = $phase?->gate_proposition_id;
            if ($phase?->cycle?->context_horizon_id !== $horizon->id
                || $gateId === null || $gateId === $node->id || ! $nodes->has($gateId)) {
                throw new DomainException('A foreign-input result requires an acting Gate in its owning Domain: '.$node->id);
            }
            $placements->put($gateId, $placements->get($gateId, collect())->push($node->id));
        }

        return $placements;
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

        $entry = $nodes->only($memberIds->all())->filter(fn ($node) => $node->tier === 'committal')
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
