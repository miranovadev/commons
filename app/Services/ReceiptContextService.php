<?php

namespace App\Services;

use App\Enums\ReceiptCapability;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptContextEvent;
use App\Models\Substrate;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ReceiptContextService
{
    public function __construct(
        private ActiveParticipantLineage $lineage,
        private PropositionOptionService $options,
        private MediationReadService $mediation,
        private PropositionStateService $state,
        private ReceiptEvidenceService $evidence,
        private PropositionMeasurementService $measurements,
    ) {}

    /** Encountered evidence only; persistent memory is not an unbounded packet inbox. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->gate_proposition_id !== null && ($context !== null
            ? ! $context->activeIds->has($phase->gate_proposition_id)
            : app(ExhaustionService::class)->unavailable($phase->gate_proposition_id))) {
            return collect();
        }
        $context ??= new ParticipationContext($phase);

        return $context->remember('receipt-context', function () use ($phase, $context): Collection {
            $gate = $context->propositions->get($phase->gate_proposition_id);
            if ($gate === null) {
                return collect();
            }
            $exposed = $this->lineage->endingAt($gate, $context)->concat([$gate])
                ->concat($this->mediation->readsFor($phase, $context))
                ->concat($this->options->gateOptionsFor($phase, $context))
                ->concat($this->options->writeOptionsFor($phase, $context))
                ->concat($this->options->instrumentOptionsFor($phase, $context))
                ->unique('id')->filter(fn ($node) => $context->activeIds->has($node->id));
            $present = $exposed->pluck('id');
            $receipts = \App\Models\Receipt::where('context_horizon_id', $phase->cycle->context_horizon_id)
                ->with(['instrumentExecution', 'consumption', 'phase'])->get();
            $loads = $context->receiptContexts->where('participant_id', $phase->cycle->participant_id)->keyBy('receipt_id');
            if ($receipts->contains('type', 'citation')) {
                $present = $present->merge($this->evidence->loadedCitationTargets($context));
            }

            $replayTargets = in_array('instrument.execute', config("phases.{$phase->kind->value}.actions", []), true)
                ? $this->options->instrumentOptionsFor($phase, $context)->pluck('id') : collect();

            return $receipts->map(function ($receipt) use ($context, $loads, $present, $replayTargets): array {
                $verified = $this->evidence->describe($receipt, $context);
                $load = $loads->get($receipt->id);
                $loaded = $load !== null && $load->cleared_at === null;
                $alreadyPresent = isset($verified['referenced_proposition_id']) && $present->contains($verified['referenced_proposition_id']);
                $status = $loaded ? 'loaded' : ($verified === null ? 'unqualified' : ($alreadyPresent ? 'already-in-context' : 'available'));

                return [
                    'id' => $receipt->id, 'proposition_id' => $receipt->instrumentExecution?->proposition_id ?? $receipt->phase->gate_proposition_id,
                    'context_horizon_id' => $receipt->context_horizon_id,
                    'availability' => $receipt->consumption === null ? 'available' : 'consumed',
                    'consumed_at' => $receipt->consumption?->consumed_at?->toISOString(),
                    'attached_proposition_id' => $receipt->consumption?->proposition_id,
                    'type' => $receipt->type, 'reference' => $receipt->reference,
                    'capabilities' => [
                        'readable' => $receipt->supports(ReceiptCapability::Readable),
                        'replayable' => $receipt->supports(ReceiptCapability::Replayable),
                    ],
                    'can_replay' => $receipt->supports(ReceiptCapability::Replayable)
                        && $replayTargets->contains($receipt->instrumentExecution->proposition_id)
                        && app(RelevantInstrumentService::class)->equivalent(
                            $context->propositions->get($receipt->instrumentExecution->proposition_id),
                            $receipt->replayInvocation(), $context,
                        ) === null,
                    'replayed_from_receipt_id' => $receipt->instrumentExecution?->replayed_from_receipt_id,
                    'observation_error' => $receipt->metadata['observation_error'] ?? null,
                    'signature' => $verified['signature'] ?? null,
                    'status' => $status, 'loaded_at' => $loaded ? $load->loaded_at->toISOString() : null,
                    ...(($loaded || $receipt->type === 'tragedy') && $verified !== null ? ['evidence' => $verified['evidence']] : []),
                ];
            })->sortBy('id')->values();
        });
    }

    /** Domain callers and the action dispatcher share the same encounter/lineage guard. */
    public function read(Participant $participant, Phase $phase, string $receiptId): ParticipantReceiptContext
    {
        return DB::transaction(function () use ($participant, $phase, $receiptId): ParticipantReceiptContext {
            $phase = $this->lock($participant, $phase);
            $context = new ParticipationContext($phase->fresh());
            $option = $this->forPhase($phase, $context)->firstWhere('id', $receiptId);
            if ($option === null || ! $option['capabilities']['readable'] || ! in_array($option['status'], ['available', 'loaded'], true)) {
                throw new DomainException('Read requires qualifying encountered evidence outside the current context.');
            }
            $load = ParticipantReceiptContext::where('participant_id', $participant->id)->where('receipt_id', $receiptId)->first();
            if ($load !== null && $load->cleared_at === null) {
                return $load;
            }
            $load ??= new ParticipantReceiptContext;
            $load->forceFill([
                'participant_id' => $participant->id, 'receipt_id' => $receiptId,
                'proposition_id' => $option['proposition_id'], 'evidence_signature' => $option['signature'],
                'loaded_at' => now(), 'cleared_at' => null,
            ])->save();
            $this->changed($load, $phase, 'loaded');

            return $load;
        });
    }

    /** Future unload boundary. No public clear action is exposed in this PR. */
    public function clear(Participant $participant, Phase $phase, string $receiptId): void
    {
        DB::transaction(function () use ($participant, $phase, $receiptId): void {
            $phase = $this->lock($participant, $phase);
            $load = ParticipantReceiptContext::active()->where('participant_id', $participant->id)->where('receipt_id', $receiptId)->first();
            if ($load === null) {
                return;
            }
            $load->forceFill(['cleared_at' => now()])->save();
            $this->changed($load, $phase, 'cleared');
        });
    }

    private function lock(Participant $participant, Phase $phase): Phase
    {
        Substrate::query()->lockForUpdate()->findOrFail($participant->substrate_id);
        Participant::query()->lockForUpdate()->findOrFail($participant->id);
        $current = Phase::query()->lockForUpdate()->findOrFail($phase->id);
        if ($current->cycle->participant_id !== $participant->id || $current->cycle->participant->substrate_id !== $participant->substrate_id
            || $current->completed_at !== null || $current->cycle->completed_at !== null) {
            throw new DomainException('Receipt context changes require this Participant’s active Phase.');
        }

        return $current;
    }

    private function changed(ParticipantReceiptContext $load, Phase $phase, string $action): void
    {
        $event = new ReceiptContextEvent;
        $event->forceFill(['participant_receipt_context_id' => $load->id, 'phase_id' => $phase->id, 'action' => $action])->save();
        $event->setRelation('phase', $phase);
        $event->setRelation('context', $load);
        app(LedgerService::class)->record($event);
        $this->measurements->recalculateIfMeasurable(Proposition::findOrFail($load->proposition_id));
        app(RealtimePublisher::class)->participant($load->participant_id);
        app(RealtimePublisher::class)->proposition($load->proposition_id);
    }
}
