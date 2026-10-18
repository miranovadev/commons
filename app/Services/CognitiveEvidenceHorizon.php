<?php

namespace App\Services;

use App\Models\Proposition;
use App\Services\Observables\CommittalLineageService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only evidence sources. Never a movement, measurement or load-eligibility graph. */
final class CognitiveEvidenceHorizon
{
    /** Additional sources only; directly displayed registers are unioned by the catalogue. */
    public function propositionIds(array $plan): Collection
    {
        $gate = Proposition::with('phase.cycle.participant')->findOrFail($plan['gate_id']);
        if ($plan['phase']['kind'] === 'distinguish') {
            return app(CommittalLineageService::class)->ancestors($gate)
                ->pluck('proposition_id')->push($gate->id)->unique()->sort()->values();
        }

        $governor = app(CommitRegionService::class)->seedFor($gate);
        if ($governor === null) {
            throw new DomainException('Cognitive evidence requires recorded Commit Region ownership.');
        }
        $substrateId = $gate->phase->cycle->participant->substrate_id;
        // Load recorded topology once, including superseded history. Container
        // membership bounds traversal; it never selects a node by itself.
        $nodes = DB::table('propositions as p')->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->join('cycles as c', 'c.id', '=', 'ph.cycle_id')
            ->join('participants as actor', 'actor.id', '=', 'c.participant_id')
            ->where('actor.substrate_id', $substrateId)
            ->get(['p.id', 'p.tier', 'p.phase_id', 'ph.gate_proposition_id', 'c.entry_committal_id', 'c.context_horizon_id'])
            ->keyBy('id');
        $edges = DB::table('supersessions')->whereIn('source_proposition_id', $nodes->keys())
            ->whereIn('target_proposition_id', $nodes->keys())->get();
        $parents = $edges->groupBy('target_proposition_id');
        $children = $edges->groupBy('source_proposition_id');
        $local = fn ($id) => ($nodes->get($id)?->tier === 'expressive'
            && $nodes[$id]->entry_committal_id === $governor->id
            && ($gate->tier === 'committal' || $nodes[$id]->context_horizon_id === $gate->phase->cycle->context_horizon_id));
        $selected = collect([$governor->id => true]);
        // One authoritative closure calculation per projection, never per Receipt.
        // Its narrower continuation graph is not our cognitive ancestry graph.
        $closed = app(TerminalContinuationService::class)->graph($substrateId)['closed'];

        // Independent traversals: never descend from an encountered ancestor.
        // Doing so would select the siblings of an already chosen continuation.
        foreach (['backward', 'forward'] as $direction) {
            $queue = [$gate->id];
            $seen = [];
            for ($i = 0; $i < count($queue); $i++) {
                $id = $queue[$i];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $selected->put($id, true);
                // A terminal node retains its associations. Only expansion stops.
                if ($direction === 'forward' && $closed->has($id)) {
                    continue;
                }
                $adjacent = $direction === 'backward' ? $parents->get($id, collect()) : $children->get($id, collect());
                foreach ($adjacent as $edge) {
                    $next = $direction === 'backward' ? $edge->source_proposition_id : $edge->target_proposition_id;
                    if ($local($next)) {
                        $queue[] = $next;
                    }
                    // Committals are hard endpoints. Only the governor and
                    // independently displayed Committals contribute evidence.
                }
            }
        }

        // An encountered genuine Reconcile supplies its two direct operands.
        // Do this AFTER traversal: a prospective operand is a leaf, not a new
        // backward/forward seed. Foreign ancestry must never enter through it.
        foreach ($selected->keys() as $id) {
            $incoming = $parents->get($id, collect());
            $node = $nodes->get($id);
            if ($incoming->count() === 2
                && $incoming->pluck('source_proposition_id')->unique()->count() === 2
                && $incoming->every(fn ($edge) => $edge->type === 'reconcile' && $edge->phase_id === $node->phase_id)
                && $incoming->contains('source_proposition_id', $node->gate_proposition_id)) {
                foreach ($incoming as $edge) {
                    $selected->put($edge->source_proposition_id, true);
                }
            }
        }

        return $selected->keys()->sort()->values();
    }
}
