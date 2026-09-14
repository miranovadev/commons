<?php

namespace App\Services\Observables;

use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionStateService;
use Illuminate\Database\Eloquent\Model;
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
            : ParticipantReceiptContext::active()->where(fn ($q) => $q->whereIn('proposition_id', $ids)->orWhereIn('receipt_id', $receipts))->get();

        return $rows->unique('participant_id')->count();
    }

    public function measurementCount(Proposition $proposition, ?ParticipationContext $context = null): int
    {
        $reclassified = $context !== null
            ? $context->children->get($proposition->id, collect())->where('type', 'reclassify')->isNotEmpty()
            : $proposition->children()->where('supersessions.type', 'reclassify')->exists();

        return $reclassified ? 0 : $this->count($proposition, $context);
    }

    /** Batch public counts using the same Commit inheritance and participant cap. */
    public function loadCounts(Collection $propositions): void
    {
        $this->state->loadCommitSources($propositions);
        $sources = $propositions->mapWithKeys(fn (Proposition $node) => [
            $node->id => $this->state->sourceIds($node),
        ]);
        $links = DB::table('proposition_receipt_links')->whereIn('proposition_id', $sources->flatten()->unique())->get();
        $rows = ParticipantReceiptContext::active()
            ->where(fn ($q) => $q->whereIn('proposition_id', $sources->flatten()->unique())->orWhereIn('receipt_id', $links->pluck('receipt_id')))
            ->get(['proposition_id', 'participant_id', 'receipt_id']);
        foreach ($propositions as $node) {
            $node->setAttribute('reinforcement_count', $rows->filter(fn ($row) => $sources[$node->id]->contains($row->proposition_id) || $links->whereIn('proposition_id', $sources[$node->id])->pluck('receipt_id')->contains($row->receipt_id))
                ->unique('participant_id')->count());
        }
    }

    /** Preserve existing structural feature weighting, now by counted participants. */
    public function weights(Collection $contributions, Substrate $substrate, ?ParticipationContext $context = null): Collection
    {
        return $contributions->mapWithKeys(fn (Model $item) => [
            $item->getKey() => 1 + ($item instanceof Proposition ? $this->count($item, $context) : 0) * $this->receiptIncrement($substrate),
        ]);
    }

    /** Existing contribution/receipt ratio remains the scale of one persistence observation. */
    public function receiptIncrement(Substrate $substrate): float
    {
        $ratio = (float) data_get($substrate->configuration, 'observables.contribution_receipt_ratio', 3);

        return 1 / (is_finite($ratio) && $ratio > 0 ? $ratio : 3);
    }
}
