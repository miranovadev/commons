<?php

namespace App\Services\Observables;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Models\ContextHorizon;
use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\Supersession;
use DomainException;

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
        $previousHorizon = ContextHorizon::query()
            ->where('substrate_id', $membership->horizon->substrate_id)
            ->where('sequence', $membership->horizon->sequence - 1)
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

        if (
            $provenanceIds->isEmpty()
            && $previousIds->contains(
                $membership->horizon->opened_by_proposition_id
            )
        ) {
            $provenanceIds->push(
                $membership->horizon->opened_by_proposition_id
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
        $propositions = Proposition::query()
            ->whereHas(
                'contextHorizonMembership',
                fn ($query) => $query->where(
                    'context_horizon_id',
                    $horizon->getKey(),
                ),
            )
            ->with(['phase.cycle', 'ledgerEntry'])
            ->get()
            ->keyBy(fn (Proposition $item) => (string) $item->getKey());
        $availableIds = $propositions->keys();
        $supersessions = Supersession::query()
            ->whereIn('source_proposition_id', $availableIds)
            ->whereIn('target_proposition_id', $availableIds)
            ->get();
        $parents = $supersessions->groupBy('target_proposition_id');
        $selected = collect();
        $frontier = collect($gateIds)
            ->filter(fn (string $id) => $propositions->has($id));

        while ($frontier->isNotEmpty()) {
            $id = (string) $frontier->shift();

            if ($selected->has($id)) {
                continue;
            }

            $selected->put($id, true);

            foreach ($parents->get($id, collect()) as $supersession) {
                $frontier->push($supersession->source_proposition_id);
            }
        }

        $lineagePropositions = $propositions
            ->only($selected->keys()->all())
            ->sortBy(
                fn (Proposition $item) => $item->ledgerEntry->sequence,
            )
            ->values();
        $selectedIds = $lineagePropositions->pluck('id');
        $lineageSupersessions = $supersessions
            ->whereIn('source_proposition_id', $selectedIds)
            ->whereIn('target_proposition_id', $selectedIds)
            ->values();

        return new DomainLineage(
            $horizon,
            $lineagePropositions,
            $lineageSupersessions,
        );
    }
}
