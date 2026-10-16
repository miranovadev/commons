<?php

namespace App\Services;

use Illuminate\Support\Arr;

/** Read activation and presentation only: never queries, summarizes or mutates load state. */
final class ParticipantPromptLayout
{
    public const VERSION = 'commons-context-c1';

    public const FORMAT_INSTRUCTION = 'The JSON input is split into two parts of one participation state. loaded_receipts supplies immutable identity, provenance and evidence for loaded Receipts; join each record by id with its current state in plan.receipts. Unloaded available unattached Receipts appear in plan.receipts as metadata only. Loaded entries there describe Participant loading state, including consumed observations; they are not shared available catalogue entries. Proposition receipts are lightweight attachment references, never evidence bodies. Only explicitly loaded Receipts grounding a current participant-facing Read expose full evidence. Loaded state persists and Reinforcement is independent of current visibility. All other Receipt references contain only id, reference, type and loaded state. Cognitive-memory Receipts remain in plan.receipts, never loaded_receipts: Tragedy exposes tragedy_memory and Proposition Search exposes proposition_collection only while memory_active. Memory grants no evidence, Reinforcement, movement, grounding or boundary authority. Optional receipt_options load/unload IDs identify current Gate evidence or available scoped cognitive memory and take effect with a forward Phase contribution (never Rest) for subsequent phases. These parts together describe the same Receipts, not additional evidence.';

    private const RECEIPT_BODY = ['id', 'proposition_id', 'context_horizon_id', 'type', 'reference', 'signature',
        'capabilities', 'replayed_from_receipt_id', 'observation_error', 'evidence'];

    /** @return array{evidence: string, user: string, loaded_receipt_count: int} */
    public function make(array $packet, array $receipts = [], array $evidenceReads = []): array
    {
        // Existing receipt order is identity order; never sort ranked evidence or lineage.
        usort($receipts, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $readIds = array_column($evidenceReads, 'id');
        $readReceipts = array_merge([], ...array_map(fn ($node) => array_column($node['receipts'] ?? [], 'id'), $evidenceReads));
        $loaded = array_values(array_filter($receipts, fn (array $receipt): bool => ($receipt['load_mode'] ?? null) !== 'memory' && ($receipt['active'] ?? false)
            && ($receipt['loaded'] ?? false)
            && (in_array($receipt['id'], $readReceipts, true) || in_array($receipt['proposition_id'] ?? null, $readIds, true))));
        // Loading an old Receipt later must not insert it ahead of older loads.
        usort($loaded, fn (array $a, array $b): int => [$a['loaded_at'] ?? '', $a['id']] <=> [$b['loaded_at'] ?? '', $b['id']]);
        $bodies = array_map(fn (array $receipt): array => Arr::only($receipt, self::RECEIPT_BODY), $loaded);
        $references = array_map(fn (array $receipt): array => Arr::only($receipt, match ($receipt['type']) {
            'tragedy' => ['id', 'type', 'reference', 'loaded', 'purpose', 'load_mode', 'memory_active', 'tragedy_memory'],
            'proposition_search' => ['id', 'type', 'reference', 'loaded', 'purpose', 'load_mode', 'memory_active', 'proposition_collection'],
            default => ['id', 'type', 'reference', 'loaded'],
        }), $receipts);

        return [
            'evidence' => $this->encode(['loaded_receipts' => $bodies]),
            'user' => $this->encode($packet, preservePlanOrder: true),
            'support' => $references === [] ? '' : $this->encode(['receipts' => $references]),
            'loaded_receipt_count' => count($loaded),
        ];
    }

    private function encode(array $value, bool $preservePlanOrder = false): string
    {
        if ($preservePlanOrder) {
            // Preserve the chosen layer order and the action schema byte/order contract.
            foreach ($value as $key => $item) {
                if ($key !== 'actions') {
                    $value[$key] = $this->canonical($item);
                }
            }
        } else {
            $value = $this->canonical($value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map($this->canonical(...), $value);
    }
}
