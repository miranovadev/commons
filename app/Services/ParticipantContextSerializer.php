<?php

namespace App\Services;

use Illuminate\Support\Arr;

/** Allowlisted model/API contract. UI additions cannot enter inference implicitly. */
final class ParticipantContextSerializer
{
    public function plan(array $plan): array
    {
        $actions = $plan['schema']['$defs']['actions'] ?? [];
        $targetIds = collect($actions)->flatMap(fn ($action) => array_merge(
            $action['properties']['proposition_id']['enum'] ?? [],
            $action['properties']['other_proposition_id']['enum'] ?? [],
        ))->unique();
        $write = collect($plan['write_options'] ?? [])
            ->concat($plan['revision_options'] ?? [])
            ->concat($plan['traversal_options'] ?? [])
            ->concat($plan['instrument_options'] ?? [])
            ->concat($plan['attestation_options'] ?? [])
            ->whereIn('id', $targetIds)->unique('id');
        // Current Gate is context even when it has no selectable Gate action.
        $gate = ($plan['phase']['kind'] === 'order' ? collect() : collect([$plan['gate']]))
            ->concat($plan['gate_options'] ?? [])->unique('id');
        $read = collect($plan['read_options'] ?? [])->unique('id');
        // Operational roles own their objects; any additional role uses schema IDs.
        $gate = $gate->whereNotIn('id', $read->pluck('id'));
        $write = $write->whereNotIn('id', $read->concat($gate)->pluck('id'));
        $lineage = collect($plan['lineage'] ?? [])
            ->whereNotIn('id', $read->concat($gate)->concat($write)->pluck('id'))->unique('id');

        return [
            'phase' => Arr::only($plan['phase'], ['id', 'number', 'kind', 'write_register']),
            'provenance_id' => $plan['provenance_id'] ?? null,
            'gate_id' => $plan['gate']['id'],
            'lineage' => $lineage->map($this->proposition(...))->values()->all(),
            'read_options' => $read->map($this->proposition(...))->values()->all(),
            'gate_options' => $gate->map($this->proposition(...))->values()->all(),
            'write_options' => $write->map($this->proposition(...))->values()->all(),
            ...(! empty($plan['commitment']) ? ['commitment' => $plan['commitment']] : []),
            ...(! empty($plan['commitment_options']) ? ['commitment_options' => $plan['commitment_options']] : []),
            'receipts' => array_map($this->receipt(...), $plan['receipts'] ?? []),
            'tragedy' => $plan['tragedy'] ?? false,
            'schema' => $plan['schema'],
        ];
    }

    /** Lifecycle details for human display do not belong in an inference prompt. */
    public function inference(array $state): array
    {
        $plan = Arr::only($state['plan'], ['phase', 'provenance_id', 'gate_id',
            'lineage', 'read_options', 'gate_options', 'write_options', 'commitment', 'commitment_options', 'tragedy', 'receipts', 'schema']);
        $plan['phase'] = Arr::only($plan['phase'], ['id', 'number', 'kind', 'write_register']);
        foreach (['lineage', 'read_options', 'gate_options', 'write_options'] as $key) {
            if (isset($plan[$key])) {
                $plan[$key] = array_map($this->proposition(...), $plan[$key]);
            }
        }

        $plan['receipts'] = array_map($this->receipt(...), $plan['receipts'] ?? []);

        return ['status' => $state['status'], 'plan' => $plan];
    }

    private function receipt(array $receipt): array
    {
        return [
            ...Arr::only($receipt, ['id', 'proposition_id', 'type', 'reference', 'signature', 'status', 'loaded_at', 'capabilities', 'can_replay', 'replayed_from_receipt_id', 'observation_error']),
            ...(isset($receipt['evidence']) ? ['evidence' => Arr::only($receipt['evidence'], [
                'proposition_id', 'statement', 'attestation_id', 'direction', 'rationale',
                'resource', 'fingerprint', 'observed_at', 'last_modified', 'content_excerpt', 'excerpt_truncated',
                'query', 'executed_at', 'results', 'provider', 'request_id',
            ])] : []),
        ];
    }

    private function proposition(array $node): array
    {
        $result = Arr::only($node, ['id', 'register', 'tier', 'statement', 'parent_ids', 'attestation_summary', 'reinforcement_count']);
        $result['observables'] = Arr::only($node['observables'] ?? [], ['correspondence', 'chirality', 'closure']);
        if (! empty($node['attestation'])) {
            $result['attestation'] = Arr::only($node['attestation'], ['direction', 'rationale']);
        }
        if (! empty($node['instruments'])) {
            $result['instruments'] = array_map(fn ($item) => [
                ...Arr::only($item, ['kind', 'reference', 'status', 'receipt_id', 'reinforcement_status', 'content_verified']),
                ...(isset($item['failure_reason']) ? ['failure_reason' => $item['failure_reason']] : []),
            ], $node['instruments']);
        }

        return $result;
    }
}
