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
            ->filter(fn ($node) => ($plan['phase']['kind'] === 'order' && $node['tier'] === 'expressive')
                || $targetIds->contains($node['id']) || ($node['spent'] ?? false)
                || ($node['continuation_exhausted'] ?? false))->unique('id');
        // Current Gate is context even when it has no selectable Gate action.
        $gate = ($plan['phase']['kind'] === 'order' || ($plan['position_exhausted'] ?? false) ? collect() : collect([$plan['gate']]))
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
            'exhausted_alternatives' => array_map($this->historical(...), $plan['exhausted_alternatives'] ?? []),
            'gate_id' => $plan['gate']['id'],
            'lineage' => $lineage->map($this->proposition(...))->values()->all(),
            'read_options' => $read->map($this->proposition(...))->values()->all(),
            'gate_options' => $gate->map($this->proposition(...))->values()->all(),
            'write_options' => $write->map($this->proposition(...))->values()->all(),
            ...(! empty($plan['commitment']) ? ['commitment' => $plan['commitment']] : []),
            ...(! empty($plan['commitment_options']) ? ['commitment_options' => $plan['commitment_options']] : []),
            'receipts' => $this->receipts($plan['receipts'] ?? []),
            'rest_available' => $plan['rest_available'] ?? false,
            'rest_reason' => $plan['rest_reason'] ?? null,
            'schema' => $plan['schema'],
        ];
    }

    /** Lifecycle details for human display do not belong in an inference prompt. */
    public function inference(array $state): array
    {
        $plan = Arr::only($state['plan'], ['phase', 'provenance_id', 'gate_id',
            'lineage', 'exhausted_alternatives', 'read_options', 'gate_options', 'write_options', 'commitment', 'commitment_options', 'rest_available', 'rest_reason', 'receipts', 'schema']);
        unset($plan['schema']['$comment']); // Publication guard metadata is not participant cognition.
        $plan['phase'] = Arr::only($plan['phase'], ['id', 'number', 'kind', 'write_register']);
        foreach (['lineage', 'read_options', 'gate_options', 'write_options'] as $key) {
            if (isset($plan[$key])) {
                $plan[$key] = array_map($this->proposition(...), $plan[$key]);
            }
        }

        $plan['receipts'] = $this->receipts($plan['receipts'] ?? []);
        if (isset($plan['exhausted_alternatives'])) {
            $plan['exhausted_alternatives'] = array_map($this->historical(...), $plan['exhausted_alternatives']);
        }

        return ['status' => $state['status'], 'plan' => $plan];
    }

    /** Shared available catalogue plus the Participant's already-admitted loaded state.
     * Loaded consumed observations remain joinable by the existing cache layout;
     * they are personal loaded context, never shared available catalogue entries.
     */
    public function receipts(array $receipts): array
    {
        return array_values(array_map($this->receipt(...), array_filter($receipts,
            fn (array $receipt): bool => ($receipt['status'] ?? null) === 'loaded'
                || (($receipt['availability'] ?? null) === 'available'
                    && empty($receipt['attached_proposition_id']) && empty($receipt['consumed_at'])))));
    }

    private function receipt(array $receipt): array
    {
        if ($receipt['type'] === 'tragedy') {
            return [
                ...Arr::only($receipt, ['id', 'loaded', 'proposition_id', 'context_horizon_id', 'availability', 'consumed_at',
                    'attached_proposition_id', 'type', 'reference', 'status', 'loaded_at', 'capabilities', 'purpose', 'load_mode']),
                'active' => false, 'can_replay' => false,
                'memory_active' => ($receipt['loaded'] ?? false) && ($receipt['memory_active'] ?? false),
                ...(($receipt['loaded'] ?? false) && ($receipt['memory_active'] ?? false) && isset($receipt['tragedy_memory'])
                    ? ['tragedy_memory' => $this->memory($receipt['tragedy_memory'])] : []),
            ];
        }

        return [
            ...Arr::only($receipt, ['id', 'loaded', 'active', 'proposition_id', 'context_horizon_id', 'availability', 'consumed_at', 'attached_proposition_id', 'type', 'reference', 'status', 'loaded_at', 'capabilities', 'can_replay', 'replayed_from_receipt_id']),
            ...(($receipt['active'] ?? false) ? Arr::only($receipt, ['signature', 'observation_error']) : []),
            ...(($receipt['active'] ?? false) && isset($receipt['evidence']) ? ['evidence' => Arr::only($receipt['evidence'], [
                'proposition_id', 'statement', 'attestation_id', 'direction', 'rationale',
                'resource', 'fingerprint', 'observed_at', 'last_modified', 'content_excerpt', 'excerpt_truncated',
                'query', 'executed_at', 'results', 'provider', 'request_id',
                'tragedy_id', 'terminal_proposition_id', 'opening_proposition_id', 'exhausted_committals',
            ])] : []),
        ];
    }

    private function memory(array $memory): array
    {
        return [
            ...Arr::only($memory, ['tragedy_id', 'receipt_id', 'governing_committal_id', 'chain_limit', 'earlier_history_omitted', 'starts_at_current_committal', 'chain_direction']),
            'committed_chain' => array_map(fn ($node) => Arr::only($node,
                ['proposition_id', 'statement', 'register', 'domain', 'depth', 'cadence', 'tension']), $memory['committed_chain']),
            'catastrophe' => Arr::only($memory['catastrophe'], ['proposition_id', 'statement', 'phase', 'register',
                'tension', 'charge', 'recorded_field_available', 'observed_at']),
        ];
    }

    private function historical(array $node): array
    {
        return Arr::only($node, ['id', 'role', 'phase_number', 'statement', 'statement_truncated',
            'tier', 'register', 'exhausted', 'basin_exhausted', 'continuation_exhausted', 'exhausted_at', 'actionable']);
    }

    private function proposition(array $node): array
    {
        $result = Arr::only($node, ['id', 'register', 'tier', 'statement', 'parent_ids', 'attestation_summary', 'reinforcement_count', 'spent', 'exploration_epoch', 'structural_position']);
        if ($node['continuation_exhausted'] ?? false) {
            $result['continuation_exhausted'] = true;
        }
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

        if (! empty($node['receipts'])) {
            $result['receipts'] = array_map(fn ($receipt) => Arr::only($receipt, ['id', 'type', 'reference', 'loaded']), $node['receipts']);
        }

        return $result;
    }
}
