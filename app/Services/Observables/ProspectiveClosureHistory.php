<?php

namespace App\Services\Observables;

use App\Enums\PhaseKind;
use App\Enums\SupersessionStrength;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\Proposition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** A bounded batch of immutable births, selected causal edges and event-born local values. */
final class ProspectiveClosureHistory
{
    private Collection $nodes;

    private Collection $births;

    private Collection $parents;

    private Collection $orientations;

    private Collection $counts;

    public function __construct(Collection $heads)
    {
        $ids = $heads->pluck('id')->unique()->values();
        if ($ids->isEmpty()) {
            throw new \InvalidArgumentException('Closure history requires at least one head.');
        }
        $placeholders = implode(',', array_fill(0, $ids->count(), '?'));
        // Load the union once, then select causal versus temporal ancestry separately
        // per head. A foreign causal parent cannot become a temporal predecessor.
        $rows = DB::select(<<<SQL
            WITH RECURSIVE links(source_id, target_id) AS (
                SELECT source_proposition_id, target_proposition_id FROM supersessions
                UNION
                SELECT temporal_predecessor_proposition_id, proposition_id
                FROM phase_profile_observations WHERE temporal_predecessor_proposition_id IS NOT NULL
            ), selected(id) AS (
                SELECT id FROM propositions WHERE id IN ({$placeholders})
                UNION
                SELECT links.source_id FROM selected
                JOIN propositions p ON p.id = selected.id
                JOIN links ON links.target_id = selected.id
                WHERE p.tier = 'expressive'
            ) SELECT id FROM selected
            SQL, $ids->all());
        $selected = collect($rows)->pluck('id');
        $this->nodes = Proposition::whereIn('id', $selected)
            ->with(['phase.cycle.contextHorizon', 'phase.cycle.participant', 'ledgerEntry'])->get()->keyBy('id');
        $this->births = DB::table('phase_profile_observations')->whereIn('proposition_id', $selected)->get()->keyBy('proposition_id');
        $this->parents = DB::table('supersessions')->whereIn('target_proposition_id', $selected)
            ->whereIn('source_proposition_id', $selected)->get()->groupBy('target_proposition_id');
        $this->orientations = DB::table('local_chirality_states')->whereIn('proposition_id', $selected)->get()->keyBy('proposition_id');
        $entries = $heads->map(fn ($head) => $head->phase->cycle->entry_committal_id)->filter()->unique();
        $this->counts = DB::table('cycles')->whereIn('entry_committal_id', $entries)
            ->selectRaw('entry_committal_id, context_horizon_id, count(*) AS aggregate, count(chirality_entry_after_sequence) AS stamped')
            ->groupBy('entry_committal_id', 'context_horizon_id')->get()
            ->keyBy(fn ($row) => $row->entry_committal_id.':'.$row->context_horizon_id);
    }

    public function interval(Proposition $head): int
    {
        return (int) $this->counts->get($head->phase->cycle->entry_committal_id.':'.$head->phase->cycle->context_horizon_id)?->aggregate;
    }

    /** Raw selected intervals, including revisions, never averages of averages. */
    public function phases(Proposition $head): array
    {
        $observations = [];
        $seen = [];
        $id = $head->id;
        while ($id !== null) {
            if (isset($seen[$id])) {
                throw new \DomainException('Temporal Closure ancestry must be acyclic.');
            }
            $seen[$id] = true;
            $node = $this->nodes->get($id);
            $row = $this->births->get($id);
            if ($node === null || $row === null) {
                throw new ClosureHistoryUnavailable('Closure requires durable selected Phase birth history.');
            }
            $observations[$id] = ['phase' => $node->phase->kind->value,
                'interval' => $row->cycle_interval === null ? null : (int) $row->cycle_interval,
                'domain' => $node->phase->cycle->contextHorizon->domain->value,
                'source' => 'expressive_birth', 'proposition_id' => $id,
                'unavailable_reason' => $row->unavailable_reason];
            $id = $row->temporal_predecessor_proposition_id;
        }
        if ($head->phase->kind === PhaseKind::Bias) {
            $birth = $this->births[$head->id];
            $clock = $this->counts->get($head->phase->cycle->entry_committal_id.':'.$head->phase->cycle->context_horizon_id);
            $interval = $birth->birth_cycle_coordinate === null || $clock === null || $clock->aggregate !== $clock->stamped
                ? null : (int) $clock->aggregate - (int) $birth->birth_cycle_coordinate;
            $observations['order:'.$head->id] = ['phase' => 7, 'interval' => $interval,
                'domain' => $head->phase->cycle->contextHorizon->domain->value,
                'source' => 'prospective_phase7', 'proposition_id' => $head->id,
                'unavailable_reason' => $interval === null ? 'missing_cycle_history' : null];
        }

        return $observations;
    }

    /** Active causal population is not the temporal Phase-profile chain. */
    public function orientations(Proposition $head): array
    {
        $selected = $superseded = [];
        $frontier = [$head->id];
        while ($frontier !== []) {
            $id = array_pop($frontier);
            if (isset($selected[$id])) {
                continue;
            }
            $node = $this->nodes->get($id);
            if ($node === null || $node->tier === 'committal') {
                continue;
            }
            $selected[$id] = $node;
            foreach ($this->parents->get($id, collect()) as $edge) {
                $frontier[] = $edge->source_proposition_id;
                if (SupersessionStrength::for($edge->type) === SupersessionStrength::Hard) {
                    $superseded[$edge->source_proposition_id] = true;
                }
            }
        }
        $observations = [];
        foreach (array_diff_key($selected, $superseded) as $id => $node) {
            $state = $this->orientations->get($id);
            if ($state === null) {
                throw new ClosureHistoryUnavailable('Closure requires authoritative event-born local Orientation.');
            }
            $observations[$id] = ['domain' => $node->phase->cycle->contextHorizon->domain->value,
                'value' => (float) $state->orientation, 'last_attestation_id' => $state->last_attestation_id];
        }

        return $observations;
    }
}
