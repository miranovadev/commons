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
            $domainNodes = $nodes->only($ids->all())->keyBy('id');
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

        $children = $realEdges
            ->filter(fn (Supersession $edge) => $nodes->has($edge->source_proposition_id)
                && $nodes->has($edge->target_proposition_id))
            ->groupBy('source_proposition_id')
            ->map(function (Collection $edges) use ($nodes): Collection {
                return $edges->sort(function (Supersession $a, Supersession $b) use ($nodes): int {
                    $aNode = $nodes->get($a->target_proposition_id);
                    $bNode = $nodes->get($b->target_proposition_id);

                    return [$this->nodeSequence($aNode), $a->target_proposition_id]
                        <=> [$this->nodeSequence($bNode), $b->target_proposition_id];
                })->values();
            });

        $row = [$entry->id];
        $depth = 0;

        while ($row !== []) {
            $next = [];
            $queued = [];

            foreach ($row as $parentId) {
                foreach ($children->get($parentId, collect()) as $edge) {
                    $childId = $edge->target_proposition_id;
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
                'Every locally-owned Domain proposition must be causally reachable from Domain provenance; '
                .'cardinal placement cannot invent a path: '.$unplaced->implode(', ')
            );
        }

        return new CardinalLayout($horizon, $entry->id, $nodes, $realEdges, $positions);
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
