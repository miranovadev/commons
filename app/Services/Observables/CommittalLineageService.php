<?php

namespace App\Services\Observables;

use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Closure's read model: creating-Cycle entry is its only ancestry edge. */
final class CommittalLineageService
{
    public function records(Proposition $position, ?ParticipationContext $context = null): Collection
    {
        $load = function () use ($position, $context): Collection {
            $seeds = $context === null ? collect([$position->id, $position->phase->cycle->entry_committal_id])
                : $context->propositions->keys()->merge($context->propositions->map(fn ($p) => $p->phase->cycle->entry_committal_id))
                    ->push($context->phase->cycle->entry_committal_id);

            return $this->recordsForIds($position->phase->cycle->participant->substrate_id, $seeds);
        };

        return $context === null ? $load() : $context->remember('closure:committed-records', $load);
    }

    /** One committed-entry expansion for a batch; callers share this canonical ancestry edge. */
    public function recordsForIds(string $substrateId, Collection $seeds): Collection
    {
        $seeds = $seeds->filter()->unique()->values();
        if ($seeds->isEmpty()) {
            return collect();
        }
        $placeholders = implode(',', array_fill(0, $seeds->count(), '?'));
        $rows = DB::select(<<<SQL
            WITH RECURSIVE chain (id, entry_committal_id) AS (
                SELECT p.id, c.entry_committal_id
                FROM commitment_snapshots s
                JOIN propositions p ON p.id = s.proposition_id
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                WHERE p.id IN ({$placeholders}) AND s.substrate_id = ?
                UNION
                SELECT p.id, c.entry_committal_id
                FROM chain child
                JOIN commitment_snapshots s ON s.proposition_id = child.entry_committal_id
                JOIN propositions p ON p.id = s.proposition_id
                JOIN phases ph ON ph.id = p.phase_id
                JOIN cycles c ON c.id = ph.cycle_id
                WHERE s.substrate_id = ?
            )
            SELECT s.*, chain.entry_committal_id, h.domain AS creating_domain,
                p.tier AS committed_tier, participant.substrate_id AS creating_substrate_id,
                h.substrate_id AS horizon_substrate_id, h.id AS creating_horizon_id,
                opened.id AS opened_horizon_id, opened.parent_context_horizon_id AS opened_parent_horizon_id,
                opened.substrate_id AS opened_substrate_id, opened.domain AS opened_domain
            FROM chain
            JOIN commitment_snapshots s ON s.proposition_id = chain.id
            JOIN propositions p ON p.id = chain.id
            JOIN phases ph ON ph.id = p.phase_id
            JOIN cycles c ON c.id = ph.cycle_id
            JOIN context_horizons h ON h.id = c.context_horizon_id
            JOIN participants participant ON participant.id = c.participant_id
            LEFT JOIN context_horizons opened ON opened.opened_by_proposition_id = p.id
            SQL, [...$seeds->all(), $substrateId, $substrateId]);

        return CommitmentSnapshot::hydrate(array_map(fn ($row) => (array) $row, $rows))->keyBy('proposition_id');
    }

    /** Direct predecessor first, Origin last. Siblings never enter this list. */
    public function ancestors(Proposition $position, ?ParticipationContext $context = null): Collection
    {
        $records = $this->records($position, $context);
        $id = $position->phase->cycle->entry_committal_id;
        $chain = collect();
        while ($id !== null) {
            if ($chain->has($id) || ! $records->has($id)) {
                throw new DomainException('Closure requires an acyclic, committed entry lineage in its substrate.');
            }
            $record = $records[$id];
            $chain->put($id, $record);
            $id = $record->entry_committal_id;
        }

        return $chain->values();
    }

    public function interval(Proposition $position, ?ParticipationContext $context = null): int
    {
        if ($position->exists) {
            return app(ProspectiveClosureService::class)->stored($position, $context)['cadence_interval'];
        }

        return $this->prospectiveInterval($position, $context);
    }

    /** Live state is consumed only by a new birth or a disposable Commit preview. */
    public function prospectiveInterval(Proposition $position, ?ParticipationContext $context = null): int
    {
        $cycle = $position->phase->cycle;
        if ($cycle->entry_committal_id === null) {
            throw new DomainException('An ordinary Commit requires an entry Committal.');
        }
        $load = function () use ($cycle, $context): Collection {
            $entries = $context === null ? collect([$cycle->entry_committal_id])
                : $context->propositions->map(fn ($p) => $p->phase->cycle->entry_committal_id)->push($context->phase->cycle->entry_committal_id);

            return Cycle::whereIn('entry_committal_id', $entries->filter()->unique())
                ->selectRaw('entry_committal_id, context_horizon_id, count(*) as aggregate')
                ->groupBy('entry_committal_id', 'context_horizon_id')->get()
                ->mapWithKeys(fn ($row) => [$row->entry_committal_id.':'.$row->context_horizon_id => $row->aggregate]);
        };
        $counts = $context === null ? $load() : $context->remember('closure:entered-basin-counts', $load);

        // Every entered Cycle counts once, including the producer, regardless of completion.
        return (int) $counts->get($cycle->entry_committal_id.':'.$cycle->context_horizon_id, 0);
    }
}
