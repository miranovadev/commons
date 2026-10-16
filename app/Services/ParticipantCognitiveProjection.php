<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Observables\CommittalLineageService;
use DomainException;
use Illuminate\Support\Collection;

/** Inference visibility only. Never supplies candidates, measurements or movement. */
final class ParticipantCognitiveProjection
{
    public function registers(array $plan): array
    {
        $pool = collect($plan['lineage'] ?? [])
            ->concat($plan['read_options'] ?? [])->concat($plan['gate_options'] ?? [])
            ->concat($plan['write_options'] ?? [])->keyBy('id');
        $gateId = $plan['gate_id'];
        $gate = $pool->get($gateId);
        if ($gate === null) {
            $gate = collect($plan['exhausted_alternatives'] ?? [])->firstWhere('id', $gateId);
        }
        if ($gate === null) {
            throw new DomainException('Cognitive context requires the recorded acting Gate.');
        }

        $kind = $plan['phase']['kind'];
        if ($kind === 'distinguish') {
            $head = Proposition::findOrFail($gateId);
            $ids = app(CommittalLineageService::class)->ancestors($head)
                ->reverse()->pluck('proposition_id')->reject(fn ($id) => $id === $gateId)->values();
            $read = $this->nodes($ids, $pool)->map(fn ($node) => [...$node, 'relation' => 'ancestor']);
        } elseif ($kind === 'mediate') {
            // The operational Read is the recorded Disperse anchor. Counterparts
            // come ONLY from the actual action, never the internal population.
            $counterparts = collect($plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum'] ?? []);
            $read = collect($plan['read_options'] ?? []);
            if ($counterparts->isNotEmpty()) {
                $read = $read->map(fn ($node) => [...$node, 'relation' => 'causal'])
                    ->concat($this->nodes($counterparts, $pool)->map(fn ($node) => [...$node, 'relation' => 'counterpart']));
            }
        } else {
            // Raw Supersession parents, including both Reconcile inputs. The
            // selected measurement/recovery path is a different contract.
            $ids = isset($gate['parent_ids']) ? collect($gate['parent_ids'])
                : Proposition::findOrFail($gateId)->parents()->orderBy('propositions.id')->pluck('propositions.id');
            $read = $this->nodes($ids, $pool);
            if ($read->count() > 1) {
                $actingId = $this->reconcileSource($gateId, $read);
                $read = $read->map(fn ($node) => [...$node,
                    'relation' => $actingId !== null && $node['id'] !== $actingId ? 'counterpart' : 'causal']);
            }
            // Order's operational Read has its own local Mediate-ancestry rule.
            if ($kind === 'order') {
                $read = $read->concat($plan['read_options'] ?? [])->unique('id');
            }
        }

        $gates = collect([$gate])->concat($plan['gate_options'] ?? [])->unique('id');
        if ($kind === 'mediate') {
            $gates = $gates->reject(fn ($node) => $node['id'] !== $gateId
                && $read->contains('id', $node['id']));
        }
        if ($gates->count() > 1) {
            $gates = $gates->map(fn ($node) => $node['id'] === $gateId ? [...$node, 'relation' => 'acting'] : $node);
        }
        // Preserve each meaningful role, even when a current Gate is also a
        // local Order Write. IDs, not visibility or relation labels, grant actions.
        $registers = ['read' => ['propositions' => $read->values()->all()],
            'gate' => ['propositions' => $gates->values()->all()],
            'write' => ['propositions' => $plan['write_options'] ?? []]];
        foreach ($plan['exhausted_alternatives'] ?? [] as $node) {
            $role = $node['role'];
            if (! isset($registers[$role])) {
                throw new DomainException('Unknown historical cognitive register.');
            }
            $nodes = collect($registers[$role]['propositions'])->keyBy('id');
            $nodes->put($node['id'], [...($nodes->get($node['id']) ?? []), ...$node]);
            $registers[$role]['propositions'] = $nodes->values()->all();
        }
        if ($kind === 'mediate' && $read->count() > 1) {
            $registers['read']['question'] = 'How do these propositions relate?';
        }

        return $registers;
    }

    /** Describe a recorded Reconcile, never infer operand roles from display order. */
    private function reconcileSource(string $gateId, Collection $read): ?string
    {
        $target = Proposition::findOrFail($gateId);
        $edges = Supersession::where('target_proposition_id', $gateId)->get();
        // Other joins and incomplete/inconsistent records retain honest causal
        // visibility. Only the two inputs of this producing operation get roles.
        if ($edges->count() !== 2 || $read->count() !== 2
            || ! $edges->every(fn ($edge) => $edge->type === 'reconcile' && $edge->phase_id === $target->phase_id)
            || $edges->pluck('source_proposition_id')->unique()->count() !== 2
            || $edges->pluck('source_proposition_id')->diff($read->pluck('id'))->isNotEmpty()) {
            return null;
        }
        // This existing resolver uses the producing Phase's recorded Gate for
        // Reconcile and returns null when that Gate does not identify an input.
        $position = app(ExplorationPositionService::class)->forProposition($target);

        return ($position['structural_position'] ?? null) === PhaseKind::Mediate->value
            ? $position['structural_source_id'] : null;
    }

    /** Hydrate only missing contextual records; consume stored measurements. */
    private function nodes(Collection $ids, Collection $pool): Collection
    {
        $ids = $ids->unique()->values();
        $missing = $ids->reject(fn ($id) => $pool->has($id));
        if ($missing->isNotEmpty()) {
            foreach (Proposition::with(['parents', 'measurement'])->whereKey($missing)->get() as $node) {
                $pool->put($node->id, [...$node->only(['id', 'statement', 'register', 'tier']),
                    'parent_ids' => $node->parents->pluck('id')->all(),
                    'observables' => $node->measurement?->only(['correspondence', 'chirality', 'closure']) ?? []]);
            }
        }

        return $ids->map(fn ($id) => $pool->get($id)
            ?? throw new DomainException('Recorded cognitive ancestry is unavailable: '.$id));
    }
}
