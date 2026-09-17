<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\ReceiptConsumption;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Shared evidence. The producing Participant grants no permission or priority. */
final class HorizonReceiptService
{
    public function availableQuery(string $horizonId): Builder
    {
        return Receipt::where('context_horizon_id', $horizonId)->whereDoesntHave('consumption')
            ->where(fn ($query) => $query->where('type', 'tragedy')
                ->orWhereHas('instrumentExecution', fn ($query) => $query->where('status', 'completed')));
    }

    public function available(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $load = fn () => $this->availableQuery($phase->cycle->context_horizon_id)
            ->with('instrumentExecution')->orderBy('created_at')->orderBy('id')->get();

        return $context === null ? $load() : $context->remember('available-horizon-receipts', $load);
    }

    /** Call within the classification transaction so any later failure rolls back attachment. */
    public function consume(Phase $phase, Proposition $source, Proposition $target, string $receiptId): Receipt
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Receipt consumption requires the reclassification transaction.');
        }
        Substrate::whereKey($phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
        $receipt = Receipt::whereKey($receiptId)->lockForUpdate()->first();
        if ($receipt === null || $receipt->context_horizon_id !== $phase->cycle->context_horizon_id
            || ! $receipt->observationComplete()
            || $receipt->consumption()->exists()) {
            throw new DomainException('Reclassification requires an unconsumed Receipt from this Horizon.');
        }
        if ($target->phase_id !== $phase->id || $source->phase->cycle->context_horizon_id !== $receipt->context_horizon_id) {
            throw new DomainException('Receipt attachment cannot cross Horizons or Phase occurrences.');
        }
        (new ReceiptConsumption)->forceFill([
            'receipt_id' => $receipt->id, 'context_horizon_id' => $receipt->context_horizon_id,
            'phase_id' => $phase->id, 'source_proposition_id' => $source->id,
            'proposition_id' => $target->id, 'consumed_at' => now(),
        ])->save();
        DB::table('proposition_receipt_links')->insert([
            'proposition_id' => $target->id, 'receipt_id' => $receipt->id, 'created_at' => now(),
        ]);
        app(RealtimePublisher::class)->proposition($target->id);
        app(RealtimePublisher::class)->horizon($receipt->context_horizon_id, $phase->cycle->participant->substrate_id,
            ['type' => 'receipt.consumed', 'receipt_id' => $receipt->id]);

        return $receipt;
    }
}
