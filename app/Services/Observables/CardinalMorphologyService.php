<?php

namespace App\Services\Observables;

use App\Domain\Observables\CardinalMorphology;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CardinalMorphologyService
{
    public function __construct(private readonly DomainCardinalLayoutService $layouts) {}

    public function current(Proposition $head, ContextHorizon $horizon, ?ParticipationContext $context = null): CardinalMorphology
    {
        $layout = $this->layouts->for($horizon, $context, $head->exists ? null : $head);
        $position = $layout->position($head->id);
        if ($position === null) {
            throw new DomainException('The measured head requires a cardinal position in its active Domain.');
        }

        return $this->ancestry($head, $horizon, $position->depth, $context);
    }

    public function reference(ContextHorizon $currentHorizon, int $depth, ?ParticipationContext $context = null): ?CardinalMorphology
    {
        if ($currentHorizon->parent_context_horizon_id === null) {
            return null;
        }

        $previous = $context?->horizons->get($currentHorizon->parent_context_horizon_id)
            ?? ContextHorizon::with('parent')->find($currentHorizon->parent_context_horizon_id);
        if ($previous === null || $currentHorizon->opened_by_proposition_id === null) {
            throw new DomainException('A non-root Correspondence reference requires the immediate parent Horizon and boundary Committal.');
        }

        $boundary = $context?->propositions->get($currentHorizon->opened_by_proposition_id)
            ?? Proposition::with(['phase.cycle.participant', 'ledgerEntry'])->find($currentHorizon->opened_by_proposition_id);
        if ($boundary === null) {
            throw new DomainException('A Correspondence reference requires its completed boundary Committal.');
        }

        $full = $this->ancestry($boundary, $previous, PHP_INT_MAX, $context);
        $primary = collect($full->positions)
            ->filter(fn ($position) => $position->horizonId === $previous->id && $position->depth <= $depth)
            ->keys();

        if ($primary->isEmpty()) {
            return new CardinalMorphology(collect(), collect(), [], $previous->id, $depth);
        }

        // Preserve any genuine foreign ancestry feeding the revealed primary
        // morphology. It keeps its own Domain coordinates; no synthetic path to
        // the primary entry is introduced.
        $keep = array_fill_keys($primary->all(), true);
        $parents = $full->supersessions->groupBy('target_proposition_id');
        $queue = $primary->values()->all();
        while ($queue !== []) {
            $id = array_shift($queue);
            foreach ($parents->get($id, collect()) as $edge) {
                $source = $edge->source_proposition_id;
                if (isset($keep[$source])) {
                    continue;
                }
                $sourcePosition = $full->positions[$source] ?? null;
                if ($sourcePosition === null) {
                    continue;
                }
                if ($sourcePosition->horizonId === $previous->id && $sourcePosition->depth > $depth) {
                    continue;
                }
                $keep[$source] = true;
                $queue[] = $source;
            }
        }

        return $this->subset($full, array_keys($keep), $depth);
    }

    private function ancestry(Proposition $head, ContextHorizon $primaryHorizon, int $primaryDepth, ?ParticipationContext $context): CardinalMorphology
    {
        $nodes = collect();
        $edges = collect();
        $positions = [];
        $seenStates = [];
        $queue = [[$head, $primaryHorizon]];

        while ($queue !== []) {
            [$node, $frame] = array_shift($queue);
            $state = $node->id.'@'.$frame->id;
            if (isset($seenStates[$state])) {
                continue;
            }
            $seenStates[$state] = true;

            $layout = $this->layouts->for($frame, $context, ! $node->exists && $frame->id === $primaryHorizon->id ? $node : null);
            $position = $layout->position($node->id);
            if ($position === null) {
                throw new DomainException('A causal contributor requires a cardinal position in its own Domain: '.$node->id);
            }

            $nodes->put($node->id, $node);
            $positions[$node->id] ??= $position;

            if ($node->id === $layout->entryId && $node->id !== $head->id) {
                continue;
            }

            foreach ($this->parentEdges($node, $context) as $edge) {
                $parent = $this->parentNode($edge, $node, $context);
                if ($parent === null) {
                    continue;
                }
                $edges->put($edge->id ?: $edge->source_proposition_id.'>'.$edge->target_proposition_id.':'.$edge->type, $edge);

                if ($parent->id === $layout->entryId) {
                    $parentFrame = $frame;
                } else {
                    $parentFrame = $this->horizonFor($parent, $context);
                }
                $queue[] = [$parent, $parentFrame];
            }
        }

        return new CardinalMorphology($nodes, $edges->values(), $positions, $primaryHorizon->id, $primaryDepth);
    }

    private function parentEdges(Proposition $node, ?ParticipationContext $context): Collection
    {
        if (! $node->exists) {
            return $node->parents->map(function ($parent) use ($node) {
                $edge = new Supersession;
                $edge->forceFill([
                    'source_proposition_id' => $parent->id,
                    'target_proposition_id' => $node->id,
                    'type' => 'commit',
                ]);

                return $edge;
            });
        }

        return $context !== null
            ? $context->parents->get($node->id, collect())->values()
            : Supersession::query()->where('target_proposition_id', $node->id)->get();
    }

    private function parentNode(Supersession $edge, Proposition $target, ?ParticipationContext $context): ?Proposition
    {
        if (! $target->exists) {
            return $target->parents->firstWhere('id', $edge->source_proposition_id);
        }

        return $context?->propositions->get($edge->source_proposition_id)
            ?? Proposition::with(['phase.cycle.participant', 'ledgerEntry'])->find($edge->source_proposition_id);
    }

    private function horizonFor(Proposition $node, ?ParticipationContext $context): ContextHorizon
    {
        $membership = $context?->memberships->get($node->id)
            ?? ContextHorizonMembership::query()->find($node->id);
        if ($membership === null) {
            throw new DomainException('A causal contributor requires Context Horizon membership: '.$node->id);
        }

        $horizon = $context?->horizons->get($membership->context_horizon_id)
            ?? ContextHorizon::find($membership->context_horizon_id);
        if ($horizon === null) {
            throw new DomainException('A causal contributor requires its owning Context Horizon.');
        }

        return $horizon;
    }

    private function subset(CardinalMorphology $morphology, array $ids, int $primaryDepth): CardinalMorphology
    {
        $set = array_fill_keys($ids, true);
        $nodes = $morphology->propositions->filter(fn ($node, $id) => isset($set[$id]));
        $edges = $morphology->supersessions->filter(fn ($edge) => isset($set[$edge->source_proposition_id]) && isset($set[$edge->target_proposition_id]))->values();
        $positions = array_filter($morphology->positions, fn ($position, $id) => isset($set[$id]), ARRAY_FILTER_USE_BOTH);

        return new CardinalMorphology($nodes, $edges, $positions, $morphology->primaryHorizonId, $primaryDepth);
    }
}
