<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Cognitive catalogue only. Neither discovery nor visibility grants load authority. */
final class ParticipantReceiptProjection
{
    public function normalize(array &$registers, array $state): array
    {
        $catalogue = collect($state['plan']['receipts'] ?? [])->keyBy('id');
        $references = collect();
        $missing = collect($registers)->flatMap(fn ($register) => $register['propositions'])
            ->filter(fn ($node) => ! isset($node['receipts']))->pluck('id')->unique();
        $hydrated = $this->referencesFor($missing);
        foreach ($registers as &$register) {
            foreach ($register['propositions'] as &$node) {
                // C1 hydrates some committed/causal Reads without Receipt references.
                // Resolve their existing contribution history; never infer attachment.
                $receipts = $node['receipts'] ?? null;
                $receipts ??= $hydrated->get($node['id'], []);
                $node['receipts'] = collect($receipts ?? [])->map(function ($receipt) use (&$catalogue) {
                    if (is_string($receipt)) {
                        return $receipt;
                    }
                    if (! $catalogue->has($receipt['id'])) {
                        $catalogue->put($receipt['id'], $receipt);
                    }

                    return $receipt['id'];
                })->unique()->sort()->values()->all();
                $references = $references->merge($node['receipts']);
                // Completed Instrument metadata repeats the Receipt catalogue.
                // Pending/failed executions without a Receipt remain informative.
                if (isset($node['instruments'])) {
                    $node['instruments'] = array_values(array_filter($node['instruments'], fn ($item) => empty($item['receipt_id'])));
                    if ($node['instruments'] === []) {
                        unset($node['instruments']);
                    }
                }
            }
            unset($node);
        }
        unset($register);
        $catalogue = $catalogue->filter(fn ($receipt) => $references->contains($receipt['id'])
            || (($receipt['load_mode'] ?? null) === 'memory'
                && (($receipt['memory_active'] ?? false) || ($receipt['availability'] ?? null) === 'available')));
        $loads = ParticipantReceiptContext::active()->where('participant_id', $state['participant']['id'])
            ->whereIn('receipt_id', $catalogue->keys())->pluck('receipt_id')->flip();
        $persisted = Receipt::whereKey($catalogue->keys())->pluck('id')->flip();

        return $catalogue->sortKeys()->map(function ($receipt) use ($loads, $persisted) {
            $loaded = $persisted->has($receipt['id']) ? $loads->has($receipt['id']) : ($receipt['loaded'] ?? false);
            $content = $receipt['tragedy_memory'] ?? $receipt['proposition_collection'] ?? $receipt['evidence'] ?? null;

            return [...Arr::only($receipt, ['id', 'type', 'reference']), 'loaded' => $loaded,
                ...($loaded && $content !== null && (($receipt['active'] ?? false) || ($receipt['memory_active'] ?? false))
                    ? ['loaded_inline_content' => $content] : [])];
        })->values()->all();
    }

    /** Batch the canonical association rules, including Commit's inherited history. */
    private function referencesFor(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }
        $nodes = Proposition::with('phase.cycle.participant')->whereKey($ids)->get();
        $state = app(PropositionStateService::class);
        $state->loadCommitSources($nodes);
        $sources = $nodes->mapWithKeys(fn ($node) => [$node->id => $state->sourceIds($node)]);
        $all = $sources->flatten()->unique();
        if ($all->isEmpty()) {
            return collect();
        }
        $attestations = Attestation::whereIn('proposition_id', $all)->get(['id', 'proposition_id']);
        $links = DB::table('proposition_receipt_links')->whereIn('proposition_id', $all)->get();
        $receipts = Receipt::where('type', '!=', 'tragedy')
            ->whereHas('instrumentExecution', fn ($q) => $q->where('status', 'completed'))
            ->where(fn ($q) => $q->whereIn('id', $links->pluck('receipt_id'))
                ->orWhere(fn ($q) => $q->where('receiptable_type', (new Proposition)->getMorphClass())->whereIn('receiptable_id', $all))
                ->orWhere(fn ($q) => $q->where('receiptable_type', (new Attestation)->getMorphClass())->whereIn('receiptable_id', $attestations->pluck('id'))))
            ->get();

        return $sources->map(function ($sourceIds) use ($receipts, $attestations, $links) {
            $linked = $links->whereIn('proposition_id', $sourceIds)->pluck('receipt_id');
            $votes = $attestations->whereIn('proposition_id', $sourceIds)->pluck('id');

            return $receipts->filter(fn ($r) => $linked->contains($r->id)
                || ($r->receiptable_type === (new Proposition)->getMorphClass() && $sourceIds->contains($r->receiptable_id))
                || ($r->receiptable_type === (new Attestation)->getMorphClass() && $votes->contains($r->receiptable_id)))
                ->map(fn ($r) => $r->only(['id', 'type', 'reference']))->values()->all();
        });
    }
}
