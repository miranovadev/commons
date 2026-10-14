<?php

namespace App\Services;

use App\Models\Proposition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Historical terminal authority. Neither current measurements nor Rest failures close a route. */
final class TerminalContinuationService
{
    /** Load actual local continuations and genuine Commit exits; foreign operands grant no route. */
    public function graph(string $substrateId): array
    {
        $nodes = DB::table('propositions as p')->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->join('cycles as c', 'c.id', '=', 'ph.cycle_id')
            ->join('participants as participant', 'participant.id', '=', 'c.participant_id')
            ->where('participant.substrate_id', $substrateId)
            ->get(['p.id', 'p.tier', 'c.entry_committal_id'])->keyBy('id');
        $edges = DB::table('supersessions')->whereIn('source_proposition_id', $nodes->keys())->get()
            ->filter(function ($edge) use ($nodes): bool {
                $source = $nodes->get($edge->source_proposition_id);
                $target = $nodes->get($edge->target_proposition_id);

                return $source?->tier === 'expressive' && $target !== null
                    && (($edge->type === 'commit' && $target->tier === 'committal')
                        || ($target->tier === 'expressive' && $source->entry_committal_id === $target->entry_committal_id));
            });
        $closed = DB::table('terminal_encounters')->whereIn('proposition_id', $nodes->keys())
            ->pluck('proposition_id')->merge(DB::table('commit_region_exhaustions')
                ->whereIn('governing_committal_id', $nodes->keys())->pluck('governing_committal_id'))->flip()->map(fn () => true);
        $children = $edges->groupBy('source_proposition_id');
        $parents = $edges->groupBy('target_proposition_id');
        $queue = $closed->keys()->all();
        for ($i = 0; $i < count($queue); $i++) {
            foreach ($parents->get($queue[$i], collect()) as $edge) {
                $source = $edge->source_proposition_id;
                if (! $closed->has($source)
                    && $children[$source]->every(fn ($child) => $closed->has($child->target_proposition_id))) {
                    $closed->put($source, true);
                    $queue[] = $source;
                }
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'closed' => $closed];
    }

    public function closed(Proposition $proposition): bool
    {
        if (! $proposition->exists) {
            return false;
        }

        return $this->graph($proposition->phase->cycle->participant->substrate_id)['closed']->has($proposition->id);
    }

    /** Persisted siblings are alternatives; absence of evidence is not historical termination. */
    public function remaining(string $seed, array $graph): Collection
    {
        $members = $graph['nodes']->filter(fn ($node) => $node->tier === 'expressive'
            && $node->entry_committal_id === $seed);
        $exits = $graph['edges']->filter(fn ($edge) => $edge->type === 'commit'
            && $members->has($edge->source_proposition_id))->pluck('target_proposition_id');

        // A later encounter of an old expressive source cannot erase its already
        // established, independently continuing Committal descendant.
        return $members->keys()->merge($exits)->unique()
            ->reject(fn ($id) => $graph['closed']->has($id))->values();
    }
}
