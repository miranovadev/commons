<?php

namespace App\Services;

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

    public function replay(Participant $participant, Phase $phase, string $receiptId): Receipt
    {
        return DB::transaction(function () use ($participant, $phase, $receiptId): Receipt {
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
            if (! ($option['can_replay'] ?? false) || $receipt === null || ! $receipt->supports(ReceiptCapability::Replayable)) {
                throw new DomainException('Replay requires a replayable encountered Receipt on an Instrument-eligible Write.');
            }

            return $this->instruments->execute($phase, $context->propositions->get($option['proposition_id']),
                $receipt->replayInvocation(), $receipt);
        });
    }
}
