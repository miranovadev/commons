<?php

namespace App\Services;

use Illuminate\Support\Arr;

/** Presentation only: never queries, selects, truncates or retains cognition. */
final class ParticipantPromptLayout
{
    public const VERSION = 'commons-prefix-v2';

    public const FORMAT_INSTRUCTION = 'The JSON input is split into two parts of one participation state. loaded_receipts supplies immutable identity, provenance and evidence for loaded Receipts; join each record by id with its current state in plan.receipts. Unloaded available unattached Receipts appear in plan.receipts as metadata only. Loaded entries there describe Participant loading state, including consumed observations; they are not shared available catalogue entries. Proposition receipts are lightweight attachment references, never evidence bodies. Only explicitly loaded Receipts expose full evidence. These parts together describe the same Receipts, not additional evidence.';

    private const RECEIPT_BODY = ['id', 'proposition_id', 'context_horizon_id', 'type', 'reference', 'signature',
        'capabilities', 'replayed_from_receipt_id', 'observation_error', 'evidence'];

    /** @return array{evidence: string, user: string, loaded_receipt_count: int} */
    public function make(array $packet): array
    {
        $receipts = $packet['plan']['receipts'];
        // Existing receipt order is identity order; never sort ranked evidence or lineage.
        usort($receipts, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $loaded = array_values(array_filter($receipts, fn (array $receipt): bool => ($receipt['status'] ?? null) === 'loaded'));
        // Loading an old Receipt later must not insert it ahead of older loads.
        usort($loaded, fn (array $a, array $b): int => [$a['loaded_at'] ?? '', $a['id']] <=> [$b['loaded_at'] ?? '', $b['id']]);
        $bodies = array_map(fn (array $receipt): array => Arr::only($receipt, self::RECEIPT_BODY), $loaded);
        $packet['plan']['receipts'] = array_map(fn (array $receipt): array => ($receipt['status'] ?? null) === 'loaded'
            ? Arr::except($receipt, array_diff(self::RECEIPT_BODY, ['id'])) : $receipt, $receipts);

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
