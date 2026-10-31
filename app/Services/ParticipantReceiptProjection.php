<?php

namespace App\Services;

use App\Enums\ReceiptCapability;
use App\Models\Attestation;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\ReceiptConsumption;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\Evidence\ReceiptSummary;
use DomainException;
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
        $direct = collect($registers)->flatMap(fn ($register) => $register['propositions'])->pluck('id');
        $extended = isset($state['plan']['gate_id'])
            ? app(CognitiveEvidenceHorizon::class)->propositionIds($state['plan']) : collect();
        // Direct roles are independent qualifying sources, including terminal
        // history and Phase 1 Writes. Never expand from these extra role IDs.
        $hydrated = $this->referencesFor($direct->merge($extended)->unique());
        foreach ($hydrated->flatten(1) as $receipt) {
            if (! $catalogue->has($receipt['id'])) {
                $catalogue->put($receipt['id'], $receipt);
            }
        }
        $references = $hydrated->flatten(1)->pluck('id');
        foreach ($registers as &$register) {
            foreach ($register['propositions'] as &$node) {
                // Preserve all displayed references and complete their recorded
                // associations without inserting extended nodes into registers.
                $receipts = collect($node['receipts'] ?? [])->concat($hydrated->get($node['id'], []));
                $node['receipts'] = $receipts->map(function ($receipt) use (&$catalogue) {
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
        $references = $references->unique()->values();
        $visible = $references->flip();
        $catalogue = $catalogue->filter(fn ($receipt) => $visible->has($receipt['id'])
            || ($receipt['type'] === 'proposition_search' && ($receipt['loaded'] ?? false))
            || (($receipt['load_mode'] ?? null) === 'memory'
                && (($receipt['memory_active'] ?? false) || ($receipt['availability'] ?? null) === 'available')));
        $loads = ParticipantReceiptContext::active()->where('participant_id', $state['participant']['id'])
            ->whereIn('receipt_id', $catalogue->keys())->pluck('receipt_id')->flip();
        $persisted = Receipt::with(['evidenceExecution', 'consumption'])->whereKey($catalogue->keys())->get()->keyBy('id');
        $reads = collect($registers['read']['propositions'] ?? [])->pluck('id')->flip();
        // Only the actual consumer qualifies. Inherited Commit associations and
        // ancestral links are references, never new consumption relationships.
        $readContext = $persisted->filter(fn ($receipt) => ! $receipt->isSpecial()
            && $receipt->supports(ReceiptCapability::Readable) && $receipt->observationComplete()
            && $receipt->consumption !== null && $reads->has($receipt->consumption->proposition_id))
            ->keys()->flip();
        if ($references->diff($catalogue->keys())->isNotEmpty()) {
            throw new DomainException('A cognitive Receipt reference has no recorded metadata.');
        }
        $evidence = app(ReceiptEvidenceService::class);
        $citations = $persisted->filter(fn ($receipt) => $receipt->type === 'citation'
            && ($loads->has($receipt->id) || $readContext->has($receipt->id)) && $visible->has($receipt->id));
        $citationBodies = $citations->isEmpty() ? collect() : $evidence->cognitiveCitations($citations,
            Phase::with('cycle.participant')->findOrFail($state['plan']['phase']['id'])->cycle->participant->substrate_id);

        $summaries = app(ReceiptSummary::class);

        return $catalogue->sortKeys()->map(function ($receipt) use ($loads, $persisted, $visible, $evidence, $citationBodies, $readContext, $summaries) {
            $model = $persisted->get($receipt['id']);
            $loaded = $model !== null ? $loads->has($receipt['id']) : ($receipt['loaded'] ?? false);
            $memory = ($receipt['load_mode'] ?? null) === 'memory';
            $contextual = $readContext->has($receipt['id']);
            $content = null;
            if (($loaded || $contextual) && ($memory ? ($receipt['memory_active'] ?? false) : ($visible->has($receipt['id']) || $receipt['type'] === 'proposition_search'))) {
                if ($memory) {
                    $content = $receipt['tragedy_memory'] ?? null;
                } elseif ($model !== null) {
                    $content = $evidence->observation($model)['evidence'] ?? $evidence->failure($model);
                    if ($model->type === 'citation') {
                        $content = $citationBodies->get($model->id)['evidence'] ?? null;
                    }
                } else {
                    $content = $receipt['evidence'] ?? $receipt['proposition_collection'] ?? null;
                }
            }

            return [...($model?->only(['id', 'type', 'reference']) ?? Arr::only($receipt, ['id', 'type', 'reference'])), 'loaded' => $loaded,
                'summary' => $summaries->forReceipt($model ?? (new Receipt)->forceFill(Arr::only($receipt, ['type', 'reference']))),
                ...($model?->evidenceExecution !== null ? ['originating_gate_id' => $model->evidenceExecution->proposition_id] : []),
                ...($model?->consumption !== null ? ['consumed_by_proposition_id' => $model->consumption->proposition_id] : []),
                ...($content !== null ? ['load_mode' => $loaded ? 'participant' : 'read_context', 'read_context' => $contextual] : []),
                ...($model?->type === 'load_file' ? ['acquisition' => $evidence->acquisition($model)] : []),
                ...($content !== null ? ['loaded_inline_content' => $content] : [])];
        })->values()->all();
    }

    /** Batch the canonical association rules, including Commit's inherited history. */
    public function referencesFor(Collection $ids): Collection
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
        $links = DB::table('proposition_receipt_links')->whereIn('proposition_id', $all)->get()
            ->concat(ReceiptConsumption::whereIn('proposition_id', $all)->get(['proposition_id', 'receipt_id']));
        // Tragedy keeps its plan-owned scope; ordinary search Receipts follow
        // these same causal associations without importing their result nodes.
        $receipts = Receipt::evidence()
            ->with('evidenceExecution:id,proposition_id')
            ->whereHas('evidenceExecution', fn ($q) => $q->recordedObservation())
            ->where(fn ($q) => $q->whereIn('id', $links->pluck('receipt_id'))
                ->orWhereHas('evidenceExecution', fn ($q) => $q->whereIn('proposition_id', $all))
                ->orWhere(fn ($q) => $q->where('receiptable_type', (new Proposition)->getMorphClass())->whereIn('receiptable_id', $all))
                ->orWhere(fn ($q) => $q->where('receiptable_type', (new Attestation)->getMorphClass())->whereIn('receiptable_id', $attestations->pluck('id'))))
            ->get(['id', 'type', 'reference', 'evidence_execution_id', 'receiptable_type', 'receiptable_id']);

        // Index associations once. Overlapping ancestry must not repeatedly scan
        // or hydrate all Receipt metadata for every selected Proposition.
        $bySource = collect();
        $add = function ($sourceId, Receipt $receipt) use ($bySource): void {
            $items = $bySource->get($sourceId, collect());
            $items->put($receipt->id, $receipt->only(['id', 'type', 'reference']));
            $bySource->put($sourceId, $items);
        };
        $votes = $attestations->keyBy('id');
        foreach ($receipts as $receipt) {
            $add($receipt->evidenceExecution->proposition_id, $receipt);
            $subject = $receipt->receiptable_type === (new Proposition)->getMorphClass()
                ? $receipt->receiptable_id : $votes->get($receipt->receiptable_id)?->proposition_id;
            if ($subject !== null) {
                $add($subject, $receipt);
            }
        }
        $receipts = $receipts->keyBy('id');
        foreach ($links as $link) {
            if ($receipts->has($link->receipt_id)) {
                $add($link->proposition_id, $receipts[$link->receipt_id]);
            }
        }

        return $sources->map(fn ($sourceIds) => $sourceIds
            ->flatMap(fn ($id) => $bySource->get($id, collect())->values())
            ->unique('id')->sortBy('id')->values()->all());
    }
}
