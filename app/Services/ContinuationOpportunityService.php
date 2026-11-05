<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Base action lawfulness only. No exploration suppression, Variant or complete plan recursion. */
final class ContinuationOpportunityService
{
    private const FORWARD_ACTIONS = ['proposition.reconcile', 'proposition.reclassify', 'proposition.commit'];

    /** Batch comparison operands for projected Phases without granting movement to them. */
    public function comparisonContext(Phase $acting, Collection $probes, ParticipationContext $context): ParticipationContext
    {
        $mediate = $probes->filter(fn ($probe) => $probe->kind === PhaseKind::Mediate
            && app(CorrespondenceGateService::class)->evaluate($probe->gate, $context)['admissible']);
        $pools = $mediate->isEmpty() ? [] : app(HomologousMediationService::class)->structuralPoolsFor($mediate, $context);
        $additional = collect();
        foreach ($pools as $pool) {
            $additional = $additional->merge($pool['reads']->pluck('id'))->merge($pool['gates']->pluck('id'));
        }
        $arrivals = $probes->whereIn('kind', [PhaseKind::Mediate, PhaseKind::Bias]);
        foreach (app(CausalCrossoverService::class)->childrenFor($arrivals) as $phaseId => $children) {
            $additional = $additional->merge($children->pluck('id'));
            $pools['crossover:'.$phaseId] = $children;
        }
        $destinations = $probes->whereNotNull('crossover_cycle_id')
            ->map(fn ($probe) => $probe->governance()->entry_committal_id)->unique();
        if ($destinations->isNotEmpty()) {
            $additional = $additional->merge(Proposition::active()->whereHas('phase.operatingCycle',
                fn ($q) => $q->whereIn('entry_committal_id', $destinations))->pluck('id'));
        }
        foreach ([PhaseKind::Bias->value => HomologousBiasService::class, PhaseKind::Order->value => HomologousOrderService::class] as $kind => $service) {
            $groups = $probes->filter(fn ($probe) => $probe->kind->value === $kind)
                ->groupBy(fn ($probe) => $probe->governance()->entry_committal_id);
            foreach ($groups as $group) {
                $pool = app($service)->populationFor($group->first(), $context);
                $additional = $additional->merge($pool->pluck('id'));
                foreach ($group as $probe) {
                    $prefix = $probe->kind === PhaseKind::Bias ? 'homologous-bias-structure:' : 'homologous-order-structure:';
                    $pools[$prefix.$probe->id] = $pool;
                }
            }
        }
        $bias = $probes->filter(fn ($probe) => $probe->kind === PhaseKind::Bias);
        foreach (app(MediatedIncomingBiasService::class)->populationsFor($bias, $context) as $key => $pool) {
            $additional = $additional->merge($pool->pluck('id'));
            $pools[$key] = $pool;
        }
        $evaluation = $additional->diff($context->propositions->keys())->isEmpty()
            ? $context : new ParticipationContext($acting, $additional->unique()->values());
        foreach ($pools as $key => $pool) {
            $evaluation->remember($key, fn () => $pool);
        }

        return $evaluation;
    }

    /** Non-Variant generation at the current viable heads, independent of Rest history. */
    public function advancingHeads(Phase $acting, Collection $heads, ParticipationContext $context): Collection
    {
        $probes = collect();
        foreach ($heads as $head) {
            if ($head->tier !== 'expressive' || $head->phase->kind === PhaseKind::Order
                || $head->phase->governance()->entry_committal_id !== $acting->governance()->entry_committal_id) {
                continue;
            }
            $next = PhaseKind::from($head->phase->kind->value + 1);
            if (array_intersect(config('phases.'.$next->value.'.actions', []), self::FORWARD_ACTIONS) === []) {
                continue;
            }
            $probes->push(app(PhasePositionService::class)->prospectiveArrival($acting, $head, $next,
                $acting->crossoverCycle, $context->propositions->get($head->phase->gate_proposition_id)));
        }
        if ($probes->isEmpty()) {
            return collect();
        }
        $evaluation = $this->comparisonContext($acting, $probes, $context);

        return $probes->filter(fn ($probe) => $this->nonVariantGenerationAvailable($probe, $evaluation))
            ->pluck('gate_proposition_id');
    }

    public function pendingEvidence(Phase $phase, ?ParticipationContext $context = null): bool
    {
        $query = fn () => EvidenceExecution::where('cycle_id', $phase->cycle_id)
            ->whereIn('status', ['pending', 'presented'])->exists();

        return $context === null ? $query() : $context->remember('pending-evidence:'.$phase->cycle_id, $query);
    }

    /**
     * Base forward generation only: never movement, reconsideration or acquisition.
     * Fresh Variant is deliberately excluded: it cannot satisfy its own frontier prerequisite.
     */
    public function nonVariantGenerationAvailable(Phase $phase, ?ParticipationContext $context = null): bool
    {
        $context ??= new ParticipationContext($phase);
        $generation = app(PhaseGenerationService::class);
        $options = app(PropositionOptionService::class);
        $actions = config('phases.'.$phase->kind->value.'.actions', []);
        foreach (array_intersect($actions, self::FORWARD_ACTIONS) as $action) {
            if (! $generation->available($phase, $action, $context)) {
                continue;
            }
            $available = match ($action) {
                'proposition.reclassify' => $phase->kind !== PhaseKind::Bias || $options->gateOptionsFor($phase, $context)->isNotEmpty(),
                'proposition.reconcile' => app(ReconciliationOptionService::class)->forPhase($phase, $context)->isNotEmpty(),
                'proposition.commit' => app(ClosureOptionService::class)->committableFor($phase, $context)->isNotEmpty(),
                default => false,
            };
            if ($available) {
                return true;
            }
        }

        return false;
    }
}
