<?php

namespace App\Services;

use App\Enums\PhaseKind;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            ...(isset($plan['chirality_admissibility']) ? ['chirality_admissibility' => $plan['chirality_admissibility']] : []),
            ...(isset($plan['correspondence_admissibility']) ? ['correspondence_admissibility' => $plan['correspondence_admissibility']] : []),
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

    /**
     * Existing Proposition Search exclusion contract. C1 keeps this operational
     * visibility boundary unchanged; search over newly hidden history is deferred.
     *
     * @return Collection<int, string>
     */
    public function visiblePropositionIds(array $plan): Collection
    {
        $visible = $this->plan($plan);
        $ids = collect([$visible['gate_id'] ?? null]);
        foreach (['lineage', 'read_options', 'gate_options', 'write_options', 'exhausted_alternatives'] as $role) {
            $ids = $ids->merge(collect($visible[$role] ?? [])->pluck('id'));
        }
        foreach ($visible['receipts'] ?? [] as $receipt) {
            $ids->push($receipt['proposition_id'] ?? null);
            $ids->push(data_get($receipt, 'evidence.proposition_id'));
            foreach (data_get($receipt, 'tragedy_memory.committed_chain', []) as $node) {
                $ids->push($node['proposition_id'] ?? null);
            }
            $ids->push(data_get($receipt, 'tragedy_memory.catastrophe.proposition_id'));
            foreach (data_get($receipt, 'proposition_collection.results', []) as $node) {
                $ids->push($node['proposition_id'] ?? null);
            }
        }

        return $ids->filter(fn ($id) => is_string($id) && $id !== '')->unique()->values();
    }

    /** Lifecycle details for human display do not belong in an inference prompt. */
    public function inference(array $state): array
    {
        $plan = $state['plan'];
        $registers = app(ParticipantCognitiveProjection::class)->registers($plan);
        // Preserve the operational Proposition.participant naming, resolving
        // authorship from creation records even for hydrated/historical Reads.
        // This batch lookup neither selects visible nodes nor grants permissions.
        $ids = collect($registers)->flatMap(fn ($register) => array_column($register['propositions'], 'id'))->unique();
        $authors = DB::table('propositions')->join('phases', 'phases.id', '=', 'propositions.phase_id')
            ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')
            ->join('participants', 'participants.id', '=', 'cycles.participant_id')
            ->whereIn('propositions.id', $ids)
            ->get(['propositions.id', 'participants.id as participant_id', 'participants.callsign'])
            ->mapWithKeys(fn ($row) => [$row->id => ['id' => $row->participant_id, 'callsign' => $row->callsign]]);
        foreach ($registers as &$register) {
            $register['propositions'] = array_map(fn ($node) => [
                ...Arr::except($this->proposition($node), [
                    'observables', 'attestation_summary', 'reinforcement_count', 'structural_position', 'exploration_epoch',
                ]),
                ...($authors->has($node['id']) ? ['participant' => $authors->get($node['id'])] : []),
                ...Arr::only($node, ['relation', 'role', 'phase_number', 'statement_truncated',
                    'exhausted', 'basin_exhausted', 'exhausted_at', 'actionable']),
            ], $register['propositions']);
        }
        unset($register);
        $registers['write']['question'] = app(ParticipantRegisterQuestions::class)->forPhase(
            PhaseKind::from($plan['phase']['number']), $plan['phase']['write_register'],
        );
        $receipts = app(ParticipantReceiptProjection::class)->normalize($registers, $state);
        $schema = $plan['schema'];
        unset($schema['$comment'], $schema['x-commons']['description']); // Publication guard remains in the transport/validator.

        return ['participant' => Arr::only($state['participant'], ['id', 'callsign', 'designation']),
            'receipts' => $receipts, ...$registers, 'actions' => $schema];
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
        if ($receipt['type'] === 'proposition_search') {
            return [
                ...Arr::only($receipt, ['id', 'loaded', 'proposition_id', 'context_horizon_id', 'availability',
                    'consumed_at', 'attached_proposition_id', 'type', 'reference', 'status', 'loaded_at', 'capabilities', 'active']),
                'can_replay' => false,
                ...(($receipt['loaded'] ?? false) && isset($receipt['proposition_collection'])
                    ? ['proposition_collection' => $this->propositionCollection($receipt['proposition_collection'])] : []),
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

    private function propositionCollection(array $collection): array
    {
        return [
            'query' => $collection['query'] ?? null,
            'limit' => $collection['limit'] ?? null,
            'head_proposition_id' => $collection['head_proposition_id'] ?? null,
            'domain' => $collection['domain'] ?? null,
            'results' => array_map(fn ($node) => Arr::only($node,
                ['proposition_id', 'statement', 'register', 'tier', 'domain', 'lineage_distance']),
                $collection['results'] ?? []),
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
