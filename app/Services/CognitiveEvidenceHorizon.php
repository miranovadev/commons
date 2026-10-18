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
            && $nodes[$id]->entry_committal_id === $governor->id);
        $selected = collect([$governor->id => true]);

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

        return $selected->keys()->sort()->values();
    }
}
