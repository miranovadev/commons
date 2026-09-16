<?php

namespace App\Services;

use App\Models\CommitmentSnapshot;
use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

/** Slow diagnostic truth: reconstruct closure from terminal Rest witnesses and ledger topology. */
final class ExhaustionAuditService
{
    public function inspect(string $substrateId): array
    {
        $nodes = Proposition::whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $substrateId))->get(['id', 'tier'])->keyBy('id');
        $edges = DB::table('supersessions')->whereIn('source_proposition_id', $nodes->keys())
            ->whereIn('target_proposition_id', $nodes->keys())->get();
        $children = $edges->groupBy('source_proposition_id');
        $states = DB::table('exhaustion_states')->where('substrate_id', $substrateId)->get()->keyBy('proposition_id');
        $origin = CommitmentSnapshot::where('substrate_id', $substrateId)->where('calculator_version', 'origin-bootstrap-v1')->value('proposition_id');
        $witnesses = $invalid = [];
        foreach ($states as $id => $state) {
            $evidence = json_decode($state->evidence, true, flags: JSON_THROW_ON_ERROR);
            if ($evidence['reason'] !== 'validated_rest') {
                continue;
            }
            $valid = $state->terminal_proposition_id === $id && DB::table('phases as phase')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->where('phase.id', $state->phase_id)->where('phase.gate_proposition_id', $id)
                ->whereNotNull('phase.completed_at')->whereNotNull('cycle.rested_at')->exists();
            if ($valid) {
                $witnesses[$id] = true;
            } else {
                $invalid[] = $id;
            }
        }
        $closed = [];
        do {
            $changed = false;
            foreach ($nodes as $id => $node) {
                if ($id === $origin || isset($closed[$id])) {
                    continue;
                }
                $outgoing = $children->get($id, collect());
                if (($outgoing->isNotEmpty() || isset($witnesses[$id]))
                    && ! $outgoing->contains(fn ($edge) => ! isset($closed[$edge->target_proposition_id]))) {
                    $closed[$id] = $node->tier === 'committal' ? 'basin' : 'excursion';
                    $changed = true;
                }
            }
        } while ($changed);
        $missing = array_values(array_diff(array_keys($closed), $states->keys()->all()));
        $unexpected = $states->filter(fn ($state, $id) => ($closed[$id] ?? null) !== $state->kind)->keys()->all();
        $horizonMismatch = DB::table('context_horizons')->where('substrate_id', $substrateId)->get()
            ->filter(fn ($h) => ($h->exhausted_at !== null) !== (($closed[$h->opened_by_proposition_id] ?? null) === 'basin'))->pluck('id')->all();
        $lineageMismatch = DB::table('origin_branches')->where('substrate_id', $substrateId)->whereNull('legacy_retired_at')->get()
            ->filter(fn ($b) => ($b->exhausted_at !== null) !== isset($closed[$b->root_proposition_id]))->pluck('root_proposition_id')->all();
        $tragedyMismatch = DB::table('origin_branches as branch')->leftJoin('tragedies as tragedy', 'tragedy.root_proposition_id', '=', 'branch.root_proposition_id')
            ->where('branch.substrate_id', $substrateId)->whereNull('branch.legacy_retired_at')->get()
            ->filter(fn ($b) => ($b->exhausted_at !== null) !== ($b->occurred_at !== null))->pluck('root_proposition_id')->all();

        return ['substrate_id' => $substrateId, 'matches' => $missing === [] && $unexpected === [] && $invalid === []
            && $horizonMismatch === [] && $lineageMismatch === [] && $tragedyMismatch === [],
            'missing' => $missing, 'unexpected' => $unexpected, 'invalid_rest_witnesses' => $invalid,
            'horizon_mismatches' => $horizonMismatch, 'lineage_mismatches' => $lineageMismatch, 'tragedy_mismatches' => $tragedyMismatch,
            'derived_states' => count($closed), 'projected_states' => $states->count(),
            'legacy_holds' => DB::table('origin_branches')->where('substrate_id', $substrateId)->whereNotNull('legacy_retired_at')->count()];
    }
}
