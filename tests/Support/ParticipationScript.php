<?php

namespace Tests\Support;

/** Stable, participant-visible contract; clocks and numerical measurements are not identity. */
final class ParticipationScript
{
    public static function state(array $state): array
    {
        $pick = static fn (array $value, array $keys): array => array_intersect_key($value, array_flip($keys));
        $result = $pick($state, ['status', 'reason', 'domain', 'current_proposition_id', 'context_horizon_id', 'horizon_sequence']);
        $result['cycle'] = $pick($state['cycle'] ?? [], ['id', 'sequence', 'context_horizon_id']);
        $plan = $state['plan'] ?? [];
        $result['plan'] = $pick($plan, ['phase', 'provenance_id', 'gate_id', 'rest_available', 'rest_reason']);
        foreach (['lineage', 'read_options', 'gate_options', 'write_options', 'exhausted_alternatives'] as $population) {
            $result['plan'][$population] = array_map(static fn (array $card): array => $pick($card, [
                'id', 'spent', 'exploration_epoch', 'continuation_exhausted', 'parent_ids',
                'attestation_summary', 'attestation',
            ]), $plan[$population] ?? []);
        }
        $result['plan']['receipts'] = array_map(static fn (array $receipt): array => $pick($receipt, [
            'id', 'proposition_id', 'context_horizon_id', 'availability', 'attached_proposition_id',
            'type', 'can_replay', 'replayed_from_receipt_id', 'status',
        ]), $plan['receipts'] ?? []);
        $result['plan']['actions'] = array_keys($plan['schema']['$defs']['actions'] ?? []);
        $result['last_commitment'] = $pick($state['last_commitment'] ?? [], [
            'proposition_id', 'depth_direction', 'cadence_direction', 'domain_before', 'domain_after',
            'closure_boundary', 'closure_history_proposition_id',
        ]);

        return $result;
    }

    /** Explicit Stage A correction to archived cognition, never to authored actions. */
    public static function stageA(array $state): array
    {
        // Retired action families are removed from archived schema expectations only.
        $state['plan']['actions'] = array_values(array_diff($state['plan']['actions'] ?? [], ['receipt.read', 'receipt.replay']));
        $state['plan']['receipts'] = array_values(array_filter($state['plan']['receipts'] ?? [],
            fn ($receipt) => $receipt['status'] === 'loaded'
                || ($receipt['availability'] === 'available' && empty($receipt['attached_proposition_id']))));
        if (($state['plan']['phase']['kind'] ?? null) === 'mediate') {
            $state['plan']['read_options'] = array_slice($state['plan']['read_options'], 0, 1);
        }

        return $state;
    }

    /** Parent references are a set; the four context populations remain ordered. */
    public static function canonicalizeParents(array $state): array
    {
        foreach (['lineage', 'read_options', 'gate_options', 'write_options', 'exhausted_alternatives'] as $population) {
            foreach ($state['plan'][$population] ?? [] as $index => $card) {
                if (isset($card['parent_ids'])) {
                    sort($card['parent_ids'], SORT_STRING);
                    $state['plan'][$population][$index]['parent_ids'] = $card['parent_ids'];
                }
            }
        }

        return $state;
    }

    /** Resolve static fixture deltas without consulting the application. */
    public static function expand(array $steps): array
    {
        // Expand only fixture data. Expected states never come from actual responses.
        foreach ($steps as $index => $step) {
            if (! array_key_exists('after_changes', $step)) {
                continue;
            }
            $expected = isset($step['before']['previous_after'])
                ? $steps[$step['before']['previous_after']]['after'] : $step['before'];
            foreach ($step['after_changes'] as $change) {
                $target = &$expected;
                foreach ($change['path'] as $key) {
                    $target = &$target[$key];
                }
                $target = $change['value'];
                unset($target);
            }
            $steps[$index]['after'] = $expected;
        }

        return $steps;
    }
}
