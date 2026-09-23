<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\ReceiptConsumption;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Shared evidence. The producing Participant grants no permission or priority. */
final class HorizonReceiptService
{
    public function available(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $context ??= new ParticipationContext($phase);

        return $context->remember('available-region-receipts', function () use ($phase, $context): Collection {
            $regions = app(ReceiptRegionService::class);
            $seed = $regions->current($phase, $context);
            if ($seed === null) {
                return collect();
            }
            $subjects = array_keys(array_filter($regions->seeds($context), fn ($id) => $id === $seed));
            $tragedies = array_keys(array_filter(app(TragedyReceiptScopeService::class)->seeds($context), fn ($id) => $id === $seed));

            return Receipt::whereDoesntHave('consumption')->where(fn ($q) => $q
                ->where(fn ($ordinary) => $ordinary->where('type', '!=', 'tragedy')->where('context_horizon_id', $phase->cycle->context_horizon_id)
                    ->whereHas('instrumentExecution', fn ($execution) => $execution->where('status', 'completed')->whereIn('proposition_id', $subjects)))
                ->orWhere(fn ($special) => $special->where('type', 'tragedy')->whereKey($tragedies)))
                ->with(['instrumentExecution', 'consumption', 'phase'])->orderBy('created_at')->orderBy('id')->get();
        });
    }

    /** Generating an observation is separate from attaching or consuming it. */
    public function instrumentedIds(Phase $phase, Collection $ids): Collection
    {
        return DB::table('instrument_executions as execution')
            ->join('receipts as receipt', 'receipt.instrument_execution_id', '=', 'execution.id')
            ->where('execution.status', 'completed')
            ->where('receipt.context_horizon_id', $phase->cycle->context_horizon_id)
            ->whereIn('execution.proposition_id', $ids)->distinct()->pluck('execution.proposition_id');
    }

    /** Call within the classification transaction so any later failure rolls back attachment. */
    public function consume(Phase $phase, Proposition $source, Proposition $target, string $receiptId): Receipt
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Receipt consumption requires the reclassification transaction.');
        }
        Substrate::whereKey($phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
        $receipt = Receipt::whereKey($receiptId)->lockForUpdate()->first();
        if ($receipt === null || ! app(ReceiptRegionService::class)->contains($receipt, $phase, new ParticipationContext($phase))
            || ! $receipt->observationComplete()
            || $receipt->consumption()->exists()) {
            throw new DomainException('Reclassification requires an unconsumed Receipt from this Commit Region.');
        }
        $homologous = $phase->kind === PhaseKind::Bias && $source->id !== $phase->gate_proposition_id
            && app(HomologousBiasService::class)->forPhase($phase)->contains('id', $source->id);
        if ($target->phase_id !== $phase->id
            || (! $homologous && $source->phase->cycle->context_horizon_id !== $receipt->context_horizon_id)) {
            throw new DomainException('Receipt attachment requires this Phase and an authorized local or homologous source.');
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
