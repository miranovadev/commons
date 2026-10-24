<?php

namespace App\Services;

use App\Domain\Evidence\InstrumentObservation;
use App\Enums\ReceiptCapability;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReceiptReplayService
{
    public function __construct(private ReceiptContextService $context, private OperationalInstrumentService $instruments) {}

    public function observe(Participant $participant, Phase $phase, string $receiptId, ?string $propositionId = null): InstrumentObservation
    {
        $context = new ParticipationContext($phase);
        $receipt = $context->receipts->firstWhere('id', $receiptId);
        $option = $this->context->forPhase($phase, $context)->firstWhere('id', $receiptId);
        if ($phase->cycle->participant_id !== $participant->id || $phase->completed_at !== null
            || $phase->cycle->completed_at !== null || ! ($option['can_replay'] ?? false)
            || ($propositionId !== null && ($option['proposition_id'] ?? null) !== $propositionId)
            || $receipt === null || ! $receipt->supports(ReceiptCapability::Replayable)) {
            throw new DomainException('Replay requires a replayable encountered Receipt on an Instrument-eligible Gate.');
        }

        return $this->instruments->observeFor($phase, $context->propositions->get($option['proposition_id']), $receipt->replayInvocation());
    }

    public function replay(Participant $participant, Phase $phase, string $receiptId, ?InstrumentObservation $observed = null, ?string $propositionId = null): Receipt
    {
        $observed ??= $this->observe($participant, $phase, $receiptId, $propositionId);

        return DB::transaction(function () use ($participant, $phase, $receiptId, $observed, $propositionId): Receipt {
            Substrate::query()->lockForUpdate()->findOrFail($participant->substrate_id);
            Participant::query()->lockForUpdate()->findOrFail($participant->id);
            $phase = Phase::query()->lockForUpdate()->findOrFail($phase->id);
            if ($phase->cycle->participant_id !== $participant->id || $phase->completed_at !== null
                || $phase->cycle->completed_at !== null || $phase->cycle->participant->substrate_id !== $participant->substrate_id) {
                throw new DomainException('Replay requires this Participant’s active Phase.');
            }
            $context = new ParticipationContext($phase);
            $option = $this->context->forPhase($phase, $context)->firstWhere('id', $receiptId);
            $receipt = $context->receipts->firstWhere('id', $receiptId);
            if (! ($option['can_replay'] ?? false) || ($propositionId !== null && ($option['proposition_id'] ?? null) !== $propositionId)
            || $receipt === null || ! $receipt->supports(ReceiptCapability::Replayable)) {
                throw new DomainException('Replay requires a replayable encountered Receipt on an Instrument-eligible Gate.');
            }

            return $this->instruments->execute($phase, $context->propositions->get($option['proposition_id']),
                $receipt->replayInvocation(), $receipt, observed: $observed);
        });
    }
}
