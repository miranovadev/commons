<?php

namespace App\Services\Observables;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Enums\ContextDomain;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ContextLineageComparisonService
{
    public function for(Proposition $proposition, ?ParticipationContext $context = null): LineageComparison
    {
        $membership = $context !== null ? $context->memberships->get($proposition->id) : ContextHorizonMembership::query()
            ->with('horizon.substrate')
            ->find($proposition->getKey());

        if ($membership === null) {
            throw new DomainException(
                'A Proposition must enter the substrate ledger before it can be measured.'
            );
        }

        $current = $this->endingAt($context !== null ? $context->horizons[$membership->context_horizon_id] : $membership->horizon, [
            $proposition->getKey(),
        ], $context);

        return $this->compare($current);
    }

    /** Build the same lineage as a Commit write, without persisting a candidate. */
    public function prospectiveCommit(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): LineageComparison
    {
        $horizon = $context !== null ? $context->horizons->get($phase->cycle->context_horizon_id) : $phase->cycle->contextHorizon;
        if ($horizon === null) {
            throw new DomainException('A prospective Commit requires its Cycle Context Horizon.');
        }
        $lineage = $this->endingAt($horizon, [$gate->getKey()], $context);
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
        $entry->sequence = $context !== null ? $context->nextSequence : (int) SubstrateLedgerEntry::query()
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
            $horizon, $lineage->propositions->concat([$candidate]), $edges, $context,
        ), [$gate->getKey()]);
    }

    /** @param list<string> $additionalSources */
    private function compare(DomainLineage $current, array $additionalSources = []): LineageComparison
    {
        $context = $current->context;
        $parentId = $current->horizon->parent_context_horizon_id;
        if ($parentId === null) {
            if ($current->horizon->sequence !== 1 || $current->horizon->opened_by_proposition_id !== null
                || $current->horizon->domain !== ContextDomain::Emergence) {
                throw new DomainException('Only the Origin Horizon may use bootstrap comparison assumptions.');
            }

            return new LineageComparison($current, null);
        }
        $previousHorizon = $context !== null ? $context->horizons->get($parentId) : $current->horizon->parent;
        if ($previousHorizon === null || $previousHorizon->substrate_id !== $current->horizon->substrate_id
            || $current->horizon->domain !== $previousHorizon->domain->opposite()) {
            throw new DomainException('A comparison requires the immediate causal parent Horizon.');
        }

        $previousIds = $context !== null ? $context->horizonMemberIds($previousHorizon->id) : ContextHorizonMembership::query()
            ->where('context_horizon_id', $previousHorizon->getKey())
            ->pluck('proposition_id');
        $provenanceIds = $context !== null ? $context->edges->whereIn('target_proposition_id', $current->propositions->pluck('id'))->whereIn('source_proposition_id', $previousIds)->pluck('source_proposition_id')->unique()->values() : Supersession::query()
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

        if ($provenanceIds->isEmpty()) {
            throw new DomainException('A non-root Horizon requires provenance in its immediate parent.');
        }

        return new LineageComparison(
            $current,
            $this->endingAt($previousHorizon, $provenanceIds->all(), $context),
        );
    }

    /**
     * @param  list<string>  $gateIds
     */
    private function endingAt(
        ContextHorizon $horizon,
        array $gateIds,
        ?ParticipationContext $context = null,
    ): DomainLineage {
        if ($context !== null) {
            $ids = $context->lineageIds($horizon->id, $gateIds);

            return new DomainLineage($horizon,
                $context->nodes($ids)->sortBy(fn ($node) => $node->ledgerEntry->sequence)->values(),
                $context->edges->whereIn('source_proposition_id', $ids)->whereIn('target_proposition_id', $ids)->values(), $context);
        }
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
