<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\CommitmentSnapshot;
use App\Models\Proposition;
use App\Models\Substrate;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PotentialChargeService
{
    public function __construct(
        private ProjectionService $projections,
    ) {}

    /**
     * Select the admissible Committal Proposition with the greatest Potential
     * Charge. Charge is the fallback when no continuation reduces Tension.
     *
     * @return array{proposition: Proposition, potential_charge: float, charge: float}
     */
    public function selectFor(Substrate $substrate): array
    {
        $snapshots = CommitmentSnapshot::query()
            ->whereBelongsTo($substrate)
            ->with('proposition')
            ->get();

        if ($snapshots->isEmpty()) {
            throw new DomainException(
                'Potential cannot select a Gate without a Committal Proposition.'
            );
        }

        $propositions = Proposition::query()
            ->whereHas(
                'phase.cycle.participant',
                fn ($query) => $query->whereBelongsTo($substrate),
            )
            ->with('measurement')
            ->get()
            ->keyBy(fn (Proposition $proposition) => $proposition->getKey());
        $edges = DB::table('supersessions')
            ->whereIn('source_proposition_id', $propositions->keys())
            ->get(['source_proposition_id', 'target_proposition_id'])
            ->groupBy('source_proposition_id');

        $ranked = $snapshots->map(function (
            CommitmentSnapshot $snapshot,
        ) use ($propositions, $edges): array {
            $potential = $this->potentialFor(
                $snapshot,
                $propositions,
                $edges,
            );

            return [
                'proposition' => $snapshot->proposition,
                'potential_charge' => $potential,
                'charge' => round(1 - abs($snapshot->tension), 7),
            ];
        })->all();

        usort($ranked, function (array $left, array $right): int {
            return ($right['potential_charge'] <=> $left['potential_charge'])
                ?: ($right['charge'] <=> $left['charge'])
                ?: strcmp(
                    $left['proposition']->getKey(),
                    $right['proposition']->getKey(),
                );
        });

        return $ranked[0];
    }

    /**
     * Potential Charge = max(0, max(|T| - |T_c|)) over measurable Expressive
     * continuations reachable before another Committal state.
     *
     * @param  Collection<string, Proposition>  $propositions
     * @param  Collection<string, Collection<int, object>>  $edges
     */
    private function potentialFor(
        CommitmentSnapshot $snapshot,
        Collection $propositions,
        Collection $edges,
    ): float {
        $frontier = collect([$snapshot->proposition_id]);
        $visited = collect([$snapshot->proposition_id => true]);
        $potential = 0.0;

        while ($frontier->isNotEmpty()) {
            $next = collect();

            foreach ($frontier as $sourceId) {
                foreach ($edges->get($sourceId, collect()) as $edge) {
                    $targetId = $edge->target_proposition_id;

                    if ($visited->has($targetId)) {
                        continue;
                    }

                    $visited->put($targetId, true);
                    $target = $propositions->get($targetId);

                    if ($target === null || $target->tier === 'committal') {
                        continue;
                    }

                    $measurement = $target->measurement;

                    if ($measurement !== null
                        && $measurement->correspondence !== null
                        && $measurement->chirality !== null
                        && $measurement->closure !== null) {
                        $projection = $this->projections->from(
                            new ObservableValues(
                                $measurement->correspondence,
                                $measurement->chirality,
                                $measurement->closure,
                            ),
                        );
                        $continuationTension = round(
                            $projection['prominence'] - $projection['turbulence'],
                            7,
                        );
                        $potential = max(
                            $potential,
                            abs($snapshot->tension) - abs($continuationTension),
                        );
                    }

                    $next->push($targetId);
                }
            }

            $frontier = $next;
        }

        return round(max(0, $potential), 7);
    }
}
