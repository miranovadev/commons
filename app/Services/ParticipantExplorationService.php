<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Personal judgment and the local structural frontier. Rest witnesses are historical observations. */
final class ParticipantExplorationService
{
    public function explored(Proposition $node, Phase $phase, ?ParticipationContext $context = null): bool
    {
        return $this->exploredIds($phase, collect([$node->id]), $context)->has($node->id);
    }

    public function exploredIds(Phase $phase, Collection $ids, ?ParticipationContext $context = null): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }
        if ($context === null || $ids->contains(fn ($id) => ! $context->propositions->has($id))) {
            // Known historical positions may lie outside the operational working set.
            // Expand only this judgment read. Movement retains its original context.
            $context = new ParticipationContext($phase, $ids, includeUnavailableAdditional: true);
        }
        $personal = $context->personalAttestationsByProposition($phase->cycle->participant_id);

        return $context->nodes($ids)->filter(fn (Proposition $node) => $this->judgedPosition($node, $personal, $context))
            ->pluck('id')->flip();
    }

    /**
     * Follow forward producing positions in this Cycle's Region/Horizon only.
     * Foreign movement is evaluated separately at its actual destination. Revisions replace
     * their source at the same position. A Committal ends this excursion.
     * Full frontier evaluation precedes every presentation limit.
     */
    public function heads(string $id, Phase $phase, ParticipationContext $context): Collection
    {
        return $context->remember('exploration-heads:'.$phase->id.':'.$id, function () use ($id, $phase, $context): Collection {
            $terminal = app(ExhaustedContinuationService::class)->ids($phase, $context);
            $seeds = $context->seedIds();
            $pending = [$id];
            $discovered = [$id => true];
            $routeParents = [];
            $seen = [];
            $heads = collect();
            while ($pending !== []) {
                $current = array_pop($pending);
                if (isset($seen[$current])) {
                    continue;
                }
                $seen[$current] = true;
                $node = $context->propositions->get($current);
                if ($node === null || ! $context->activeIds->has($current) || $terminal->has($current)) {
                    continue;
                }
                $children = $context->children->get($current, collect());
                $next = $children->filter(function ($edge) use ($node, $phase, $context, $seeds, $terminal): bool {
                    $target = $context->propositions->get($edge->target_proposition_id);
                    if ($target === null || ! $context->activeIds->has($target->id) || $terminal->has($target->id)
                        || $target->phase->governance()->context_horizon_id !== $phase->governance()->context_horizon_id
                        || ($seeds[$target->id] ?? null) !== $phase->governance()->entry_committal_id) {
                        return false;
                    }
                    if ($edge->type === 'revise') {
                        return $target->phase->kind === $node->phase->kind && $target->register === $node->register;
                    }
                    if ($node->tier === 'committal' && $node->id !== $phase->governance()->entry_committal_id) {
                        return false;
                    }
                    $expected = $node->tier === 'committal' ? PhaseKind::Distinguish->value : $node->phase->kind->value + 1;

                    return $target->phase->kind->value === $expected
                        && ($target->phase->gate_proposition_id === $node->id || $edge->type === 'reconcile'
                            || ($edge->type === 'reclassify' && $target->phase->kind === PhaseKind::Bias
                                && app(PhasePositionService::class)->matches($target, $phase, PhaseKind::Bias)))
                        && $target->register === $phase->governance()->contextHorizon->domain->registerFor($target->phase->kind);
                });
                if ($next->isNotEmpty()) {
                    foreach ($next->sortBy('target_proposition_id') as $edge) {
                        $target = $edge->target_proposition_id;
                        $routeParents[$target][$current] = true;
                        if (! isset($discovered[$target])) {
                            $discovered[$target] = true;
                            $pending[] = $target;
                        }
                    }
                } elseif ($children->where('type', '!=', 'variant')->isEmpty()) {
                    // Hard-superseded nodes are history, never independent heads.
                    $heads->put($current, $node);
                }
            }

            $context->remember('exploration-route-parents:'.$phase->id.':'.$id, fn () => $routeParents);

            return $heads->sortKeys();
        });
    }

    /** Current expressive positions on a viable local route, before presentation limits. */
    public function judgmentFrontier(string $root, Phase $phase, ParticipationContext $context): Collection
    {
        $heads = $this->heads($root, $phase, $context);
        $parents = $context->remember('exploration-route-parents:'.$phase->id.':'.$root, fn () => []);
        $pending = $heads->keys()->all();
        $visited = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            array_push($pending, ...array_keys($parents[$id] ?? []));
        }

        // A Revision replaces its source at one position. Its earlier expression
        // must not become a second quota for a Participant who judged the replacement.
        return $context->nodes(array_keys($visited))->reject(fn ($node) => $context->children->get($node->id, collect())
            ->contains(fn ($edge) => $edge->type === 'revise' && isset($visited[$edge->target_proposition_id])))
            ->pluck('id');
    }

    /** Selected movement route, never the union of Reconciliation evidence ancestors. */
    public function participationComplete(Proposition $head, Phase $phase, ParticipationContext $context, ?string $via = null): bool
    {
        return $context->remember('route-complete:'.$phase->id.':'.$head->id.':'.$via, function () use ($head, $phase, $context, $via): bool {
            $overrides = [];
            if ($via !== null) {
                if (! $this->heads($via, $phase, $context)->has($head->id)) {
                    return false;
                }
                $overrides = $context->remember('exploration-route-parents:'.$phase->id.':'.$via, fn () => []);
            }
            // An actual arrival wins over a multi-parent producer's acting Gate.
            $arrivals = $context->remember('exploration-arrivals:'.$phase->cycle_id, fn () => DB::table('phases as arrival')
                ->join('phases as previous', fn ($join) => $join->on('previous.cycle_id', '=', 'arrival.cycle_id')
                    ->whereRaw('previous.kind = arrival.kind - 1')->whereNotNull('previous.completed_at'))
                ->where('arrival.cycle_id', $phase->cycle_id)
                ->get(['previous.gate_proposition_id as source', 'arrival.gate_proposition_id as target'])
                ->pluck('source', 'target'));
            $personal = $context->personalAttestationsByProposition($phase->cycle->participant_id);
            if (! $this->judgedPosition($head, $personal, $context)) {
                return false;
            }
            $terminal = app(ExhaustedContinuationService::class)->ids($phase, $context);
            $seen = [];
            $pending = [$head->id];
            while ($pending !== []) {
                $id = array_pop($pending);
                if (isset($seen[$id])) {
                    continue;
                }
                $node = $context->propositions->get($id);
                if ($node === null) {
                    return false;
                }
                $seen[$id] = true;
                if ($node->id !== $phase->governance()->entry_committal_id
                    && ($node->tier === 'committal'
                        || $node->phase->governance()->context_horizon_id !== $phase->governance()->context_horizon_id
                        || ($context->seedIds()[$node->id] ?? null) !== $phase->governance()->entry_committal_id)) {
                    continue;
                }
                $active = $context->activeIds->has($node->id) && ! $terminal->has($node->id)
                    && $context->children->get($node->id, collect())->where('type', '!=', 'variant')->isEmpty();
                if ($active && $node->tier === 'expressive' && $node->phase->kind !== PhaseKind::Order
                    && ! $this->judgedPosition($node, $personal, $context)) {
                    return false;
                }
                if ($node->tier === 'committal') {
                    continue;
                }
                if (isset($overrides[$node->id])) {
                    array_push($pending, ...array_keys($overrides[$node->id]));

                    continue;
                }
                $parents = $context->parents->get($node->id, collect())->pluck('source_proposition_id')->unique()->values()->all();
                $selected = $arrivals->get($node->id) ?? $node->phase->gate_proposition_id;
                if (count($parents) > 1 && ! in_array($selected, $parents, true)) {
                    return false; // Incomplete arrival provenance cannot prove a personal route.
                }
                $parent = count($parents) === 1 ? $parents[0] : $selected;
                if ($parent !== null) {
                    $pending[] = $parent;
                }
            }

            return true;
        });
    }

    /** Revision is reconsideration of a position, not a new first-judgment quota. */
    private function judgedPosition(Proposition $node, Collection $personal, ParticipationContext $context): bool
    {
        $pending = [$node->id];
        $seen = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if ($personal->has($id)) {
                return true;
            }
            foreach ($context->parents->get($id, collect())->where('type', 'revise') as $edge) {
                $source = $context->propositions->get($edge->source_proposition_id);
                if ($source !== null && $source->phase->kind === $node->phase->kind && $source->register === $node->register
                    && $source->phase->governance()->entry_committal_id === $node->phase->governance()->entry_committal_id
                    && $source->phase->governance()->context_horizon_id === $node->phase->governance()->context_horizon_id) {
                    $pending[] = $source->id;
                }
            }
        }

        return false;
    }
}
