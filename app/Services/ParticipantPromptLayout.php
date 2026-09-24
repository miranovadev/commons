<?php

namespace App\Services;

use Illuminate\Support\Arr;

/** Read activation and presentation only: never queries, summarizes or mutates load state. */
final class ParticipantPromptLayout
{
    public const VERSION = 'commons-prefix-v2';

    public const FORMAT_INSTRUCTION = 'The JSON input is split into two parts of one participation state. loaded_receipts supplies immutable identity, provenance and evidence for loaded Receipts; join each record by id with its current state in plan.receipts. Unloaded available unattached Receipts appear in plan.receipts as metadata only. Loaded entries there describe Participant loading state, including consumed observations; they are not shared available catalogue entries. Proposition receipts are lightweight attachment references, never evidence bodies. Only explicitly loaded Receipts grounding a current participant-facing Read expose full evidence. Loaded state persists and Reinforcement is independent of current visibility. All other Receipt references contain only id, reference, type and loaded state. Optional receipt_options load/unload IDs belong to the current Gate and take effect with a forward Phase contribution (never Rest) for subsequent phases. These parts together describe the same Receipts, not additional evidence.';

    private const RECEIPT_BODY = ['id', 'proposition_id', 'context_horizon_id', 'type', 'reference', 'signature',
        'capabilities', 'replayed_from_receipt_id', 'observation_error', 'evidence'];

    /** @return array{evidence: string, user: string, loaded_receipt_count: int} */
    public function make(array $packet): array
    {
        $receipts = $packet['plan']['receipts'];
        // Existing receipt order is identity order; never sort ranked evidence or lineage.
        usort($receipts, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $readIds = array_column($packet['plan']['read_options'] ?? [], 'id');
        $readReceipts = array_merge([], ...array_map(fn ($node) => array_column($node['receipts'] ?? [], 'id'), $packet['plan']['read_options'] ?? []));
        $loaded = array_values(array_filter($receipts, fn (array $receipt): bool => $receipt['type'] !== 'tragedy' && ($receipt['active'] ?? false)
            && ($receipt['loaded'] ?? false)
            && (in_array($receipt['id'], $readReceipts, true) || in_array($receipt['proposition_id'] ?? null, $readIds, true))));
        // Loading an old Receipt later must not insert it ahead of older loads.
        usort($loaded, fn (array $a, array $b): int => [$a['loaded_at'] ?? '', $a['id']] <=> [$b['loaded_at'] ?? '', $b['id']]);
        $bodies = array_map(fn (array $receipt): array => Arr::only($receipt, self::RECEIPT_BODY), $loaded);
        $packet['plan']['receipts'] = array_map(fn (array $receipt): array => Arr::only($receipt, $receipt['type'] === 'tragedy'
            ? ['id', 'type', 'reference', 'loaded', 'purpose'] : ['id', 'type', 'reference', 'loaded']), $receipts);

        // The historical path keeps its exact ordered objects, including measurements.
        // It can still change; it is deliberately outside the loaded-evidence boundary.
        $plan = $packet['plan'];
        $packet['plan'] = [];
        foreach (['lineage', 'provenance_id', 'exhausted_alternatives', 'read_options', 'gate_options', 'write_options',
            'commitment', 'commitment_options', 'receipts', 'gate_id', 'phase', 'rest_available', 'rest_reason', 'schema'] as $key) {
            if (array_key_exists($key, $plan)) {
                $packet['plan'][$key] = $plan[$key];
            }
        }

        return [
            'evidence' => $this->encode(['loaded_receipts' => $bodies]),
            'user' => $this->encode($packet, preservePlanOrder: true),
            'loaded_receipt_count' => count($loaded),
        ];
    }

    private function encode(array $value, bool $preservePlanOrder = false): string
    {
        if ($preservePlanOrder) {
            // Preserve the chosen layer order and the action schema byte/order contract.
            foreach ($value['plan'] as $key => $item) {
                if ($key !== 'schema') {
                    $value['plan'][$key] = $this->canonical($item);
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
