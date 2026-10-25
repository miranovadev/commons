<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** A slot is Horizon + producing kind + actual structural source, never a Phase occurrence. */
final class StructuralSourceService
{
    public function forPhase(Phase $phase): array
    {
        if ($phase->gate_proposition_id === null) {
            throw new DomainException('Exploration requires a structural source.');
        }

        return ['context_horizon_id' => $phase->cycle->context_horizon_id,
            'structural_position' => $phase->kind->value, 'structural_source_id' => $phase->gate_proposition_id];
    }

    public function forProposition(Proposition $node, ?ParticipationContext $context = null): ?array
    {
        return $node->tier === 'committal' ? null : $this->forIds(collect([$node->id]), $context)->get($node->id);
    }

    /** Load only requested provenance and its revision predecessors, in batches. */
    public function forIds(Collection $ids, ?ParticipationContext $context = null): Collection
    {
        if ($context !== null && $ids->every(fn ($id) => $context->propositions->has($id))) {
            return $context->remember('structural-sources', fn () => $this->resolve(
                $context->propositions->map(fn ($node) => (object) [
                    'id' => $node->id, 'tier' => $node->tier, 'kind' => $node->phase->kind->value,
                    'context_horizon_id' => $node->phase->cycle->context_horizon_id,
                    'gate_proposition_id' => $node->phase->gate_proposition_id,
                ]), $context->parents,
            ))->only($ids->all());
        }
        $nodes = $edges = collect();
        $pending = $ids->unique()->values();
        while ($pending->isNotEmpty()) {
            $batch = DB::table('propositions as node')->join('phases as phase', 'phase.id', '=', 'node.phase_id')
                ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')->whereIn('node.id', $pending)
                ->get(['node.id', 'node.tier', 'phase.kind', 'phase.gate_proposition_id', 'cycle.context_horizon_id'])->keyBy('id');
            $incoming = DB::table('supersessions')->whereIn('target_proposition_id', $batch->keys())->get();
            $nodes = $nodes->union($batch);
            $edges = $edges->concat($incoming);
            $pending = $incoming->where('type', 'revise')->pluck('source_proposition_id')->diff($nodes->keys())->unique()->values();
        }

        return $this->resolve($nodes, $edges->groupBy('target_proposition_id'))->only($ids->all());
    }

    public function key(array $position): string
    {
        return $position['context_horizon_id'].':'.$position['structural_position'].':'.$position['structural_source_id'];
    }

    private function resolve(Collection $nodes, Collection $parents): Collection
    {
        $resolved = $visiting = [];
        $resolve = function (string $id) use (&$resolve, &$resolved, &$visiting, $nodes, $parents): ?array {
            if (array_key_exists($id, $resolved)) {
                return $resolved[$id];
            }
            if (isset($visiting[$id])) {
                throw new DomainException('Structural provenance contains a revision cycle.');
            }
            $node = $nodes->get($id);
            if ($node === null || $node->tier !== 'expressive') {
                return $resolved[$id] = null;
            }
            $visiting[$id] = true;
            $inputs = $parents->get($id, collect());
            $revisions = $inputs->where('type', 'revise');
            if ($revisions->isNotEmpty()) {
                if ($inputs->count() !== 1) {
                    throw new DomainException('A revision must preserve one structural position.');
                }
                $position = $resolve($revisions->sole()->source_proposition_id);
                if ($position !== null && ($position['context_horizon_id'] !== $node->context_horizon_id
                    || $position['structural_position'] !== (int) $node->kind)) {
                    throw new DomainException('A revision cannot change its structural position.');
                }
            } else {
                // Selected Reclassify sources come from their edge, not an incidental Phase Gate.
                // Reconcile has multiple inputs: only the recorded traversed Gate disambiguates it.
                $source = $inputs->count() === 1 ? $inputs->first()->source_proposition_id
                    : ($inputs->every(fn ($edge) => $edge->type === 'reconcile')
                        ? $inputs->firstWhere('source_proposition_id', $node->gate_proposition_id)?->source_proposition_id : null);
                $position = $source === null ? null : ['context_horizon_id' => $node->context_horizon_id,
                    'structural_position' => (int) $node->kind, 'structural_source_id' => $source];
            }
            unset($visiting[$id]);

            return $resolved[$id] = $position;
        };
        foreach ($nodes->keys() as $id) {
            $resolve($id);
        }

        return collect($resolved);
    }
}
