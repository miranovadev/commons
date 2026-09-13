<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
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
                ->unique('id');
            $present = $exposed->pluck('id');
            $receipts = $exposed->flatMap(fn ($node) => $this->state->receipts($node, $context))->unique('id');
            $loads = $context->receiptContexts->where('participant_id', $phase->cycle->participant_id)->keyBy('receipt_id');
            $knownReceipts = $context->receipts->keyBy('id');
            foreach ($loads->whereNull('cleared_at') as $load) {
                $known = $knownReceipts->get($load->receipt_id);
                $reference = $known === null ? null : ($this->evidence->describe($known, $context)['referenced_proposition_id'] ?? null);
                if ($reference !== null) {
                    $present->push($reference);
                }
            }

            return $receipts->map(function ($receipt) use ($context, $loads, $present): array {
                $verified = $this->evidence->describe($receipt, $context);
                $load = $loads->get($receipt->id);
                $loaded = $load !== null && $load->cleared_at === null;
                $alreadyPresent = isset($verified['referenced_proposition_id']) && $present->contains($verified['referenced_proposition_id']);
                $status = $loaded ? 'loaded' : ($verified === null ? 'unqualified' : ($alreadyPresent ? 'already-in-context' : 'available'));

                return [
                    'id' => $receipt->id, 'proposition_id' => $receipt->instrumentExecution->proposition_id,
                    'type' => $receipt->type, 'reference' => $receipt->reference,
                    'signature' => $verified['signature'] ?? null,
                    'status' => $status, 'loaded_at' => $loaded ? $load->loaded_at->toISOString() : null,
                    ...($loaded ? ['evidence' => $verified['evidence']] : []),
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
            if ($option === null || ! in_array($option['status'], ['available', 'loaded'], true)) {
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
        PropositionMeasurement::whereHas('proposition.phase.cycle.participant', fn ($q) => $q
            ->where('substrate_id', $phase->cycle->participant->substrate_id))->update(['calculator_version' => null]);
        $this->measurements->recalculateIfMeasurable(Proposition::findOrFail($load->proposition_id));
        app(RealtimePublisher::class)->participant($load->participant_id);
        app(RealtimePublisher::class)->proposition($load->proposition_id);
    }
}
