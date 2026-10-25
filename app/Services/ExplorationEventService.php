<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Immutable personal Rest encounters; the field version guards publication, never reopening. */
final class ExplorationEventService
{
    public function inspect(Phase $phase): array
    {
        $substrateId = $phase->cycle->participant->substrate_id;
        $version = app(PotentialProjectionService::class)->assertCurrent($substrateId);
        $context = new ParticipationContext($phase);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $witness = isset($plan['schema']['$defs']['actions']['participant.rest'])
            ? $this->completionWitness($phase, $context) : null;

        return ['phase_id' => $phase->id, 'participant_id' => $phase->cycle->participant_id,
            'position_id' => $phase->gate_proposition_id, 'field_version' => $version,
            'rest_reason' => $plan['rest_reason'] ?? 'no_available_move', 'witness' => $witness,
            'stable' => $version === (int) Substrate::whereKey($substrateId)->value('field_version')];
    }

    /** Base encounter proof shared with Rest planning; never calls InputPlan. */
    public function completionWitness(Phase $phase, ParticipationContext $context): ?array
    {
        $witness = null;
        $gate = $context->propositions->get($phase->gate_proposition_id);
        $arrival = $phase->cycle->phases()->where('kind', $phase->kind->value - 1)->whereNotNull('completed_at')->first();
        if ($gate?->tier === 'expressive'
            && $phase->kind->value >= PhaseKind::Mediate->value
            && $gate->phase->kind->value === $phase->kind->value - 1 && $arrival !== null
            && app(ParticipantExplorationService::class)->heads($gate->id, $phase, $context)->keys()->all() === [$gate->id]
            && ! app(ContinuationOpportunityService::class)->nonVariantGenerationAvailable($phase, $context)
            && ! app(ContinuationOpportunityService::class)->unfinishedEvidence($phase, $context)) {
            $state = app(PropositionStateService::class);
            $judgment = $state->attestations(collect([$gate]), $context)->first();
            $receipt = $state->evidenceReceipts($gate, $context)
                ->concat(app(HorizonReceiptService::class)->ordinaryAvailable($phase, $context))
                ->first(fn ($receipt) => $receipt->evidenceExecution?->status === 'completed');
            if ($judgment !== null && $receipt !== null) {
                $witness = ['contract' => 1, 'head_id' => $gate->id, 'arrival_phase_id' => $arrival->id,
                    'arrival_source_id' => $arrival->gate_proposition_id, 'attestation_id' => $judgment->id,
                    'receipt_id' => $receipt->id, 'meaningful_continuation' => false];
            }
        }

        return $witness;
    }

    /** Called inside the same Substrate/Participant/Cycle/Phase locks as Rest completion. */
    public function publish(Phase $phase, array $proof): void
    {
        DB::transaction(function () use ($phase, $proof): void {
            $substrate = Substrate::whereKey($phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
            $rest = $phase->fresh();
            $cycle = $rest->cycle;
            if ($proof['witness'] === null || ! $proof['stable'] || $proof['field_version'] !== (int) $substrate->field_version
                || $proof['phase_id'] !== $rest->id || $proof['position_id'] !== $rest->gate_proposition_id
                || $proof['participant_id'] !== $cycle->participant_id || $rest->completed_at === null
                || $cycle->rested_at === null || $cycle->rest_reason?->value !== $proof['rest_reason']) {
                return;
            }
            $arrival = Phase::find($proof['witness']['arrival_phase_id'] ?? null);
            if ($arrival === null || $arrival->cycle_id !== $rest->cycle_id || $arrival->completed_at === null
                || $arrival->kind->value + 1 !== $rest->kind->value
                || $arrival->gate_proposition_id !== ($proof['witness']['arrival_source_id'] ?? null)
                || ($proof['witness']['head_id'] ?? null) !== $rest->gate_proposition_id
                || ($proof['witness']['meaningful_continuation'] ?? true) !== false) {
                return;
            }
            if (DB::table('exploration_events')->insertOrIgnore([
                'phase_id' => $rest->id, 'proposition_id' => $rest->gate_proposition_id,
                'arrival_phase_id' => $proof['witness']['arrival_phase_id'],
                'witness' => json_encode($proof['witness'], JSON_THROW_ON_ERROR),
                'observed_field_version' => $proof['field_version'], 'recorded_at' => now(),
            ])) {
                app(RealtimePublisher::class)->participant($cycle->participant_id);
            }
        });
    }
}
