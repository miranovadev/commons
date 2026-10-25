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
            ->get(['event.*', 'cycle.entry_committal_id', 'cycle.context_horizon_id'])->groupBy('proposition_id'));
        if ($events->isEmpty()) {
            return collect();
        }

        $evaluation = $this->withProbeBoundaries($phase, $context, $events->keys());

        return $ids->filter(function ($id) use ($phase, $context, $events, $evaluation): bool {
            return $context->remember('explored:'.$phase->id.':'.$id, function () use ($id, $phase, $context, $events, $evaluation): bool {
                $heads = $this->heads($id, $phase, $context);

                return $heads->isNotEmpty() && $heads->every(function ($head) use ($phase, $events, $evaluation): bool {
                    $encounters = $events->get($head->id, collect());
                    if (! $encounters->contains(fn ($event) => $event->context_horizon_id === $phase->cycle->context_horizon_id
                        && $event->entry_committal_id === $phase->cycle->entry_committal_id)) {
                        return false;
                    }

                    return ! $this->canProgress($head, $phase, $evaluation);
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
                    array_push($pending, ...$next->pluck('target_proposition_id')->all());
                } elseif ($children->where('type', '!=', 'variant')->isEmpty()) {
                    // Hard-superseded nodes are history, never independent heads.
                    $heads->put($current, $node);
                }
            }

            return $heads->sortKeys();
        });
    }

    /** Pure look-ahead: never creates a Cycle/Phase or recursively asks InputPlan. */
    public function canProgress(Proposition $head, Phase $acting, ParticipationContext $context): bool
    {
        return $context->remember('head-progression:'.$acting->id.':'.$head->id, function () use ($head, $acting, $context): bool {
            if ($head->tier !== 'expressive' || $head->phase->kind === PhaseKind::Order) {
                return false;
            }
            $phase = $this->probe($head, $acting, $context);
            if (app(ContinuationOpportunityService::class)->advancing($phase, $context)) {
                return true;
            }
            // Revise advances this head from its producing position, not from
            // the next Phase. Ownership and prior-Cycle Refute stay authoritative.
            if (! in_array('proposition.revise', config('phases.'.$head->phase->kind->value.'.actions', []), true)) {
                return false;
            }
            if ($head->phase->cycle->participant_id !== $acting->cycle->participant_id
                || ! $context->effectiveAttestations(collect([$head->id]))->contains(fn ($vote) => $vote->direction === 'refute'
                    && $vote->phase->cycle->participant_id === $acting->cycle->participant_id
                    && $vote->phase->cycle->sequence < $acting->cycle->sequence)) {
                return false;
            }
            $source = app(StructuralSourceService::class)->forProposition($head, $context);
            $gate = $context->propositions->get($source['structural_source_id'] ?? null);
            if ($gate === null) {
                return false;
            }
            $revision = $this->probe($gate, $acting, $context);

            return app(PropositionOptionService::class)->revisionOptionsFor($revision, $acting->cycle->participant, $context)
                ->contains('id', $head->id);
        });
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
                ->get(['event.*', 'cycle.entry_committal_id', 'cycle.context_horizon_id'])->groupBy('proposition_id'));
            $encounter = $events->get($head->id, collect())->first(fn ($event) => $event->entry_committal_id === $acting->cycle->entry_committal_id
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
            $heads = $context->nodes($ids)->filter(fn ($head) => $head->tier === 'expressive'
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
