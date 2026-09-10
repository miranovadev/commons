<?php

namespace App\Services\Observables;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\Phase;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ContextLineageComparisonService
{
    public function for(Proposition $proposition): LineageComparison
    {
        $membership = ContextHorizonMembership::query()
            ->with('horizon.substrate')
            ->find($proposition->getKey());

        if ($membership === null) {
            throw new DomainException(
                'A Proposition must enter the substrate ledger before it can be measured.'
            );
        }

        $current = $this->endingAt($membership->horizon, [
            $proposition->getKey(),
        ]);

        return $this->compare($current);
    }

    /** Build the same lineage as a Commit write, without persisting a candidate. */
    public function prospectiveCommit(Proposition $gate, Phase $phase): LineageComparison
    {
        $horizon = ContextHorizon::query()
            ->with('substrate')
            ->where('substrate_id', $phase->cycle->participant->substrate_id)
            ->whereNull('closed_at')->firstOrFail();
        $lineage = $this->endingAt($horizon, [$gate->getKey()]);
        $candidate = new Proposition;
        $candidate->forceFill([
            'id' => (string) str()->ulid(), 'phase_id' => $phase->getKey(),
            'register' => $phase->write_register, 'tier' => 'committal',
            'statement' => $gate->statement,
        ]);
        $candidate->setRelation('phase', $phase);
        $candidate->setRelation('parents', collect([$gate]));
        $candidate->setRelation('commitSources', collect([$gate]));
        $entry = new SubstrateLedgerEntry;
        $entry->sequence = (int) SubstrateLedgerEntry::query()
            ->where('substrate_id', $horizon->substrate_id)->max('sequence') + 1;
        $candidate->setRelation('ledgerEntry', $entry);
        $edges = clone $lineage->supersessions;
        if ($lineage->propositions->contains('id', $gate->getKey())) {
            $edge = new Supersession;
            $edge->forceFill([
                'source_proposition_id' => $gate->getKey(),
                'target_proposition_id' => $candidate->getKey(), 'type' => 'commit',
            ]);
            $edges->push($edge);
        }

        return $this->compare(new DomainLineage(
            $horizon, $lineage->propositions->concat([$candidate]), $edges,
        ), [$gate->getKey()]);
    }

    /** @param list<string> $additionalSources */
    private function compare(DomainLineage $current, array $additionalSources = []): LineageComparison
    {
        $previousHorizon = ContextHorizon::query()
            ->where('substrate_id', $current->horizon->substrate_id)
            ->where('sequence', $current->horizon->sequence - 1)
            ->first();

        if ($previousHorizon === null) {
            return new LineageComparison($current, null);
        }

        $previousIds = ContextHorizonMembership::query()
            ->where('context_horizon_id', $previousHorizon->getKey())
            ->pluck('proposition_id');
        $provenanceIds = Supersession::query()
            ->whereIn(
                'target_proposition_id',
                $current->propositions->pluck('id'),
            )
            ->whereIn('source_proposition_id', $previousIds)
            ->pluck('source_proposition_id')
            ->unique()
            ->values();
        $provenanceIds = $provenanceIds->merge(
            $previousIds->intersect($additionalSources),
        )->unique()->values();

        if (
            $provenanceIds->isEmpty()
            && $previousIds->contains(
                $current->horizon->opened_by_proposition_id
            )
        ) {
            $provenanceIds->push(
                $current->horizon->opened_by_proposition_id
            );
        }

        return new LineageComparison(
            $current,
            $this->endingAt($previousHorizon, $provenanceIds->all()),
        );
    }

    /**
     * @param  list<string>  $gateIds
     */
    private function endingAt(
        ContextHorizon $horizon,
        array $gateIds,
    ): DomainLineage {
        if ($gateIds === []) {
            return new DomainLineage($horizon, collect(), collect());
        }

        $placeholders = implode(
            ', ',
            array_fill(0, count($gateIds), '?'),
        );
        $rows = DB::select(
            <<<SQL
                WITH RECURSIVE lineage (id) AS (
                    SELECT proposition_id
                    FROM context_horizon_memberships
                    WHERE context_horizon_id = ?
                      AND proposition_id IN ({$placeholders})

                    UNION

                    SELECT supersessions.source_proposition_id
                    FROM supersessions
                    INNER JOIN lineage
                        ON lineage.id = supersessions.target_proposition_id
                    INNER JOIN context_horizon_memberships
                        ON context_horizon_memberships.proposition_id
                            = supersessions.source_proposition_id
                    WHERE context_horizon_memberships.context_horizon_id = ?
                )
                SELECT id FROM lineage
            SQL,
            [
                $horizon->getKey(),
                ...$gateIds,
                $horizon->getKey(),
            ],
        );
        $lineageIds = collect($rows)->pluck('id');
        $propositions = Proposition::query()
            ->whereIn('id', $lineageIds)
            ->with(['phase.cycle', 'ledgerEntry'])
            ->get()
            ->sortBy(
                fn (Proposition $item) => $item->ledgerEntry->sequence,
            )
            ->values();
        $supersessions = Supersession::query()
            ->whereIn('source_proposition_id', $lineageIds)
            ->whereIn('target_proposition_id', $lineageIds)
            ->get();

        return new DomainLineage(
            $horizon,
            $propositions,
            $supersessions,
        );
    }
}
