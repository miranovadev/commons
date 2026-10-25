<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;

/** Base action lawfulness only. No exploration suppression, Variant or complete plan recursion. */
final class ContinuationOpportunityService
{
    /** Evidence shortage is not a completed exploration encounter. This probe grants no action. */
    public function awaitingEvidence(Phase $phase, ParticipationContext $context): bool
    {
        if ($phase->kind === PhaseKind::Bias) {
            $generation = app(PhaseGenerationService::class);

            return ! $generation->available($phase, 'proposition.reclassify', $context)
                && $generation->available($phase, 'proposition.reclassify', $context, withoutReceipt: true);
        }
        if ($phase->kind === PhaseKind::Order) {
            return app(ClosureOptionService::class)->forPhase($phase, $context)->contains(function ($node) use ($phase, $context): bool {
                $requirements = app(CommitmentAdmissibilityService::class)->evaluate($node, $phase, $context)['requirements'];

                return ! $requirements['receipt'] && collect($requirements)->except('receipt')->every(fn ($met) => $met === true);
            });
        }

        return false;
    }

    public function advancing(Phase $phase, ?ParticipationContext $context = null): bool
    {
        $context ??= new ParticipationContext($phase);
        $generation = app(PhaseGenerationService::class);
        $options = app(PropositionOptionService::class);
        $actions = config('phases.'.$phase->kind->value.'.actions', []);
        foreach ($actions as $action) {
            if (in_array($action, ['proposition.variant', 'attestation.create'], true)
                || ! $generation->available($phase, $action, $context)) {
                continue;
            }
            $available = match ($action) {
                'proposition.reclassify' => $phase->kind !== PhaseKind::Bias || $options->gateOptionsFor($phase, $context)->isNotEmpty(),
                'proposition.reconcile' => app(ReconciliationOptionService::class)->forPhase($phase, $context)->isNotEmpty(),
                'proposition.revise' => $options->revisionOptionsFor($phase, $phase->cycle->participant, $context)->isNotEmpty(),
                'proposition.traverse' => $options->traversalOptionsFor($phase, $context, base: true)->isNotEmpty(),
                'proposition.commit' => app(ClosureOptionService::class)->committableFor($phase, $context)->isNotEmpty(),
                'instrument.execute' => $options->instrumentOptionsFor($phase, $context)->isNotEmpty(),
                'preparation.start' => true,
                default => false,
            };
            if ($available) {
                return true;
            }
        }

        return false;
    }
}
