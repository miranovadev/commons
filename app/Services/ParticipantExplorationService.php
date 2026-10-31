<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Personal history over a complete, directed continuation frontier. Never a structural flag. */
final class ParticipantExplorationService
{
    public function explored(Proposition $node, Phase $phase, ?ParticipationContext $context = null): bool
    {
        return $this->exploredIds($phase, collect([$node->id]), $context)->has($node->id);
    }

    public function exploredIds(Phase $phase, Collection $ids, ?ParticipationContext $context = null): Collection
    {
        $context ??= new ParticipationContext($phase);
        $events = $context->remember('personal-exploration-events:'.$phase->cycle->participant_id, fn () => DB::table('exploration_events as event')
            ->join('phases as rest', 'rest.id', '=', 'event.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'rest.cycle_id')
            ->where('cycle.participant_id', $phase->cycle->participant_id)
            ->whereIn('event.proposition_id', $context->propositions->keys())
            ->get(['event.*', 'cycle.entry_committal_id', 'cycle.context_horizon_id', 'rest.cycle_id as encounter_cycle_id'])->groupBy('proposition_id'));
        if ($events->isEmpty()) {
            return collect();
        }

        $evaluation = $this->withProbeBoundaries($phase, $context, $events->keys());

        return $ids->filter(function ($id) use ($phase, $context, $events, $evaluation): bool {
            return $context->remember('explored:'.$phase->id.':'.$id, function () use ($id, $phase, $context, $events, $evaluation): bool {
                $heads = $this->heads($id, $phase, $context);

                return $heads->isNotEmpty() && $heads->every(function ($head) use ($id, $phase, $context, $events, $evaluation): bool {
                    $encounters = $this->encounters($head, $phase, $context, $events);

                    return $encounters->contains(fn ($event) => $event->context_horizon_id === $phase->cycle->context_horizon_id
                        && $event->entry_committal_id === $phase->cycle->entry_committal_id
                        && $this->participationComplete($head, $phase, $context, $id, $event->encounter_cycle_id))
                        && ! $this->canGenerateFromHead($head, $phase, $evaluation);
                });
            });
        })->flip();
    }

    /**
     * Follow forward producing positions in this Cycle's Region/Horizon only.
     * A foreign Reconcile operand grants no movement; local results retain their
     * existing Attestation routes. Revisions replace
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
                        || $target->phase->cycle->context_horizon_id !== $phase->cycle->context_horizon_id
                        || ($seeds[$target->id] ?? null) !== $phase->cycle->entry_committal_id) {
                        return false;
                    }
                    if ($edge->type === 'revise') {
                        return $target->phase->kind === $node->phase->kind && $target->register === $node->register;
                    }
                    if ($node->tier === 'committal' && $node->id !== $phase->cycle->entry_committal_id) {
                        return false;
                    }
                    $expected = $node->tier === 'committal' ? PhaseKind::Distinguish->value : $node->phase->kind->value + 1;

                    return $target->phase->kind->value === $expected
                        && ($target->phase->gate_proposition_id === $node->id || $edge->type === 'reconcile')
                        && $target->register === $phase->cycle->contextHorizon->domain->registerFor($target->phase->kind);
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

    /** Selected movement route, never the union of Reconciliation evidence ancestors. */
    public function participationComplete(Proposition $head, Phase $phase, ParticipationContext $context, ?string $via = null, ?string $encounterCycleId = null): bool
    {
        return $context->remember('route-complete:'.$phase->id.':'.$head->id.':'.$via.':'.$encounterCycleId, function () use ($head, $phase, $context, $via, $encounterCycleId): bool {
            $overrides = [];
            if ($via !== null) {
                $this->heads($via, $phase, $context);
                if (! $this->heads($via, $phase, $context)->has($head->id)) {
                    return false;
                }
                $overrides = $context->remember('exploration-route-parents:'.$phase->id.':'.$via, fn () => []);
            }
            // An actual arrival wins over a multi-parent producer's acting Gate.
            $arrivals = $context->remember('exploration-arrivals', function () use ($phase, $context): Collection {
                // All encounter routes in one batch; no history query per head.
                $cycleIds = DB::table('exploration_events as event')->join('phases as rest', 'rest.id', '=', 'event.phase_id')
                    ->whereIn('event.proposition_id', $context->propositions->keys())->select('rest.cycle_id');

                return DB::table('phases as arrival')
                    ->join('cycles as cycle', 'cycle.id', '=', 'arrival.cycle_id')
                    ->join('phases as previous', fn ($join) => $join->on('previous.cycle_id', '=', 'arrival.cycle_id')
                        ->whereRaw('previous.kind = arrival.kind - 1')->whereNotNull('previous.completed_at'))
                    ->where('cycle.participant_id', $phase->cycle->participant_id)
                    ->where(fn ($q) => $q->where('cycle.id', $phase->cycle_id)->orWhereIn('cycle.id', $cycleIds))
                    ->get(['arrival.cycle_id', 'arrival.gate_proposition_id as target', 'previous.gate_proposition_id as source'])
                    ->groupBy('cycle_id')->map(fn ($rows) => $rows->pluck('source', 'target'));
            })->get($encounterCycleId ?? $phase->cycle_id, collect());
            $personal = $context->remember('exploration-personal-judgments:'.$phase->cycle->participant_id,
                fn () => $context->effectiveAttestations($context->propositions->keys())
                    ->filter(fn ($a) => $a->phase->cycle->participant_id === $phase->cycle->participant_id)->keyBy('proposition_id'));
            if (! $this->judgedPosition($head, $personal, $context)) {
                return false;
            }
            $shared = app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context)->isNotEmpty();
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
                if ($node->id !== $phase->cycle->entry_committal_id
                    && ($node->tier === 'committal'
                        || $node->phase->cycle->context_horizon_id !== $phase->cycle->context_horizon_id
                        || ($context->seedIds()[$node->id] ?? null) !== $phase->cycle->entry_committal_id)) {
                    continue;
                }
                $active = $context->activeIds->has($node->id) && ! $terminal->has($node->id)
                    && $context->children->get($node->id, collect())->where('type', '!=', 'variant')->isEmpty();
                if ($active) {
                    if ($node->tier === 'expressive'
                        && app(PropositionOptionService::class)->attestationEvidenceAvailable($node, $node->phase->kind, $shared, $context)
                        && ! $this->judgedPosition($node, $personal, $context)) {
                        return false;
                    }

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
                    && $source->phase->cycle->entry_committal_id === $node->phase->cycle->entry_committal_id) {
                    $pending[] = $source->id;
                }
            }
        }

        return false;
    }

    /** Pure look-ahead: never creates a Cycle/Phase or recursively asks InputPlan. */
    public function canGenerateFromHead(Proposition $head, Phase $acting, ParticipationContext $context): bool
    {
        return $context->remember('head-generation:'.$acting->id.':'.$head->id, function () use ($head, $acting, $context): bool {
            if ($head->tier !== 'expressive' || $head->phase->kind === PhaseKind::Order) {
                return false;
            }
            $phase = $this->probe($head, $acting, $context);

            return app(ContinuationOpportunityService::class)->nonVariantGenerationAvailable($phase, $context);
        });
    }

    /** Revision changes the same position; it does not erase a personal encounter there. */
    private function encounters(Proposition $head, Phase $phase, ParticipationContext $context, Collection $events): Collection
    {
        $ids = collect();
        $pending = [$head->id];
        while ($pending !== []) {
            $id = array_pop($pending);
            if ($ids->has($id)) {
                continue;
            }
            $ids->put($id, true);
            foreach ($context->parents->get($id, collect())->where('type', 'revise') as $edge) {
                $source = $context->propositions->get($edge->source_proposition_id);
                if ($source !== null && $source->phase->kind === $head->phase->kind && $source->register === $head->register
                    && $source->phase->cycle->context_horizon_id === $phase->cycle->context_horizon_id
                    && ($context->seedIds()[$source->id] ?? null) === $phase->cycle->entry_committal_id) {
                    $pending[] = $source->id;
                }
            }
        }

        return $ids->keys()->flatMap(fn ($id) => $events->get($id, collect()));
    }

    private function probe(Proposition $head, Phase $acting, ParticipationContext $context): Phase
    {
        $phase = new Phase;
        $kind = $head->tier === 'committal' ? PhaseKind::Distinguish : PhaseKind::from($head->phase->kind->value + 1);
        $phase->forceFill(['id' => 'exploration:'.$acting->id.':'.$head->id, 'cycle_id' => $acting->cycle_id,
            'kind' => $kind, 'gate_proposition_id' => $head->id,
            'write_register' => $acting->cycle->contextHorizon->domain->registerFor($kind)]);
        $phase->setRelation('cycle', $acting->cycle);
        $phase->setRelation('gate', $head);
        if ($kind === PhaseKind::Mediate) {
            $events = $context->remember('personal-exploration-events:'.$acting->cycle->participant_id, fn () => DB::table('exploration_events as event')
                ->join('phases as rest', 'rest.id', '=', 'event.phase_id')->join('cycles as cycle', 'cycle.id', '=', 'rest.cycle_id')
                ->where('cycle.participant_id', $acting->cycle->participant_id)
                ->whereIn('event.proposition_id', $context->propositions->keys())
                ->get(['event.*', 'cycle.entry_committal_id', 'cycle.context_horizon_id', 'rest.cycle_id as encounter_cycle_id'])->groupBy('proposition_id'));
            $encounter = $this->encounters($head, $acting, $context, $events)->first(fn ($event) => $event->entry_committal_id === $acting->cycle->entry_committal_id
                && $event->context_horizon_id === $acting->cycle->context_horizon_id);
            $witness = $encounter === null ? [] : json_decode($encounter->witness, true, flags: JSON_THROW_ON_ERROR);
            $phase->setRelation('arrivalAnchor', $context->propositions->get($witness['arrival_source_id'] ?? null));
        }

        return $phase;
    }

    /** Hydrate all relevant comparison boundaries together, not one graph per head. */
    private function withProbeBoundaries(Phase $phase, ParticipationContext $context, Collection $ids): ParticipationContext
    {
        return $context->remember('exploration-boundaries:'.$phase->id, function () use ($phase, $context, $ids): ParticipationContext {
            $heads = $ids->flatMap(fn ($id) => $this->heads($id, $phase, $context)->values())->unique('id')->filter(fn ($head) => $head->tier === 'expressive'
                && $head->phase->kind !== PhaseKind::Order
                && $head->phase->cycle->context_horizon_id === $phase->cycle->context_horizon_id
                && ($context->seedIds()[$head->id] ?? null) === $phase->cycle->entry_committal_id
                && $this->heads($head->id, $phase, $context)->keys()->all() === [$head->id]);
            $probes = $heads->map(fn ($head) => $this->probe($head, $phase, $context));
            $mediate = $probes->filter(fn ($probe) => $probe->kind === PhaseKind::Mediate
                && app(CorrespondenceGateService::class)->evaluate($probe->gate, $context)['admissible']);
            $pools = $mediate->isEmpty() ? [] : app(HomologousMediationService::class)->structuralPoolsFor($mediate, $context);
            $additional = collect();
            foreach ($pools as $pool) {
                $additional = $additional->merge($pool['reads']->pluck('id'))->merge($pool['gates']->pluck('id'));
            }
            foreach ([PhaseKind::Bias->value => HomologousBiasService::class, PhaseKind::Order->value => HomologousOrderService::class] as $kind => $service) {
                $group = $probes->filter(fn ($probe) => $probe->kind->value === $kind);
                if ($group->isEmpty()) {
                    continue;
                }
                // Same entry, Domain and producing position have one canonical population.
                $pool = app($service)->populationFor($group->first(), $context);
                $additional = $additional->merge($pool->pluck('id'));
                foreach ($group as $probe) {
                    $prefix = $probe->kind === PhaseKind::Bias ? 'homologous-bias-structure:' : 'homologous-order-structure:';
                    $pools[$prefix.$probe->id] = $pool;
                }
            }
            $bias = $probes->filter(fn ($probe) => $probe->kind === PhaseKind::Bias);
            foreach (app(MediatedIncomingBiasService::class)->populationsFor($bias, $context) as $key => $pool) {
                $additional = $additional->merge($pool->pluck('id'));
                $pools[$key] = $pool;
            }
            $evaluation = $additional->diff($context->propositions->keys())->isEmpty()
                ? $context : new ParticipationContext($phase, $additional->unique()->values());
            foreach ($pools as $key => $pool) {
                $evaluation->remember($key, fn () => $pool);
            }

            return $evaluation;
        });
    }
}
