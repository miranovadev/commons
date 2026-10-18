<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\Participation\ParticipationContext;
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
        $catalogue = $catalogue->filter(fn ($receipt) => $references->contains($receipt['id'])
            || (($receipt['load_mode'] ?? null) === 'memory'
                && (($receipt['memory_active'] ?? false) || ($receipt['availability'] ?? null) === 'available')));
        $loads = ParticipantReceiptContext::active()->where('participant_id', $state['participant']['id'])
            ->whereIn('receipt_id', $catalogue->keys())->pluck('receipt_id')->flip();
        $persisted = Receipt::with('instrumentExecution')->whereKey($catalogue->keys())->get()->keyBy('id');
        $visible = $references->unique()->flip();
        if ($references->diff($catalogue->keys())->isNotEmpty()) {
            throw new DomainException('A cognitive Receipt reference has no recorded metadata.');
        }
        $evidence = app(ReceiptEvidenceService::class);
        $context = null;

        return $catalogue->sortKeys()->map(function ($receipt) use ($loads, $persisted, $visible, $evidence, &$context, $state) {
            $model = $persisted->get($receipt['id']);
            $loaded = $model !== null ? $loads->has($receipt['id']) : ($receipt['loaded'] ?? false);
            $memory = ($receipt['load_mode'] ?? null) === 'memory';
            $content = null;
            if ($loaded && ($memory ? ($receipt['memory_active'] ?? false) : $visible->has($receipt['id']))) {
                if ($memory) {
                    $content = $receipt['tragedy_memory'] ?? $receipt['proposition_collection'] ?? null;
                } elseif ($model !== null) {
                    $content = $evidence->observation($model)['evidence'] ?? $evidence->failure($model);
                    if ($model->type === 'citation') {
                        $context ??= new ParticipationContext(Phase::findOrFail($state['plan']['phase']['id']));
                        $content = $evidence->citation($model, $context)['evidence'] ?? null;
                    }
                } else {
                    $content = $receipt['evidence'] ?? null;
                }
            }

            return [...($model?->only(['id', 'type', 'reference']) ?? Arr::only($receipt, ['id', 'type', 'reference'])), 'loaded' => $loaded,
                ...($content !== null ? ['loaded_inline_content' => $content] : [])];
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
        // Special memory keeps its existing plan-owned scope; causal discovery
        // must not turn an old memory association into a new active memory.
        $receipts = Receipt::whereNotIn('type', ['tragedy', 'proposition_search'])
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
