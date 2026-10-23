<?php

namespace App\Services\Observables;

use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionStateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReinforcementService
{
    public function __construct(private readonly PropositionStateService $state) {}

    /** Count state formation, never Receipt volume or attenuated evidence. */
    public function count(Proposition $proposition, ?ParticipationContext $context = null): int
    {
        $ids = $this->state->sourceIds($proposition, $context);
        $receipts = $context !== null ? $context->receiptLinks->whereIn('proposition_id', $ids)->pluck('receipt_id')
            : DB::table('proposition_receipt_links')->whereIn('proposition_id', $ids)->pluck('receipt_id');
        $rows = $context !== null ? $context->reinforcements->filter(fn ($row) => $ids->contains($row->proposition_id) || $receipts->contains($row->receipt_id))
            : ParticipantReceiptContext::active()->whereHas('receipt', fn ($q) => $q->whereNotIn('type', Receipt::SPECIAL_RECEIPT_TYPES))->where(fn ($q) => $q->whereIn('proposition_id', $ids)->orWhereIn('receipt_id', $receipts))->get();

        return $rows->unique('participant_id')->count();
    }

    /** Batch public counts using the same Commit inheritance and participant cap. */
    public function loadCounts(Collection $propositions): void
    {
        $this->state->loadCommitSources($propositions);
        $sources = $propositions->mapWithKeys(fn (Proposition $node) => [
            $node->id => $this->state->sourceIds($node),
        ]);
        $links = DB::table('proposition_receipt_links')->whereIn('proposition_id', $sources->flatten()->unique())->get();
        $rows = ParticipantReceiptContext::active()->whereHas('receipt', fn ($q) => $q->whereNotIn('type', Receipt::SPECIAL_RECEIPT_TYPES))
            ->where(fn ($q) => $q->whereIn('proposition_id', $sources->flatten()->unique())->orWhereIn('receipt_id', $links->pluck('receipt_id')))
            ->get(['proposition_id', 'participant_id', 'receipt_id']);
        foreach ($propositions as $node) {
            $node->setAttribute('reinforcement_count', $rows->filter(fn ($row) => $sources[$node->id]->contains($row->proposition_id) || $links->whereIn('proposition_id', $sources[$node->id])->pluck('receipt_id')->contains($row->receipt_id))
                ->unique('participant_id')->count());
        }
    }
}
