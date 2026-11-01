<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Base action lawfulness only. No exploration suppression, Variant or complete plan recursion. */
final class ContinuationOpportunityService
{
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
            ? $context : new ParticipationContext($acting, $additional->unique()->values());
        foreach ($pools as $key => $pool) {
            $evaluation->remember($key, fn () => $pool);
        }

        return $evaluation;
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
        foreach (array_intersect($actions, ['proposition.reconcile', 'proposition.reclassify', 'proposition.commit']) as $action) {
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
