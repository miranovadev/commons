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
                $snapshot->proposition_id,
                $snapshot->tension,
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
     * Search complete distance shells before ranking. Recovery never uses the
     * initial-entry Charge fallback, or a disconnected source of Potential.
     *
     * @return array{proposition: Proposition, potential_charge: float, charge: float, distance: int}|null
     */
    public function recoverFrom(Proposition $position): ?array
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $propositions = Proposition::query()
            ->whereHas('phase.cycle.participant', fn ($query) => $query
                ->where('substrate_id', $substrateId))
            ->with(['measurement', 'commitmentSnapshot'])
            ->get()->keyBy('id');
        $edges = DB::table('supersessions')
            ->whereIn('source_proposition_id', $propositions->keys())
            ->whereIn('target_proposition_id', $propositions->keys())
            ->get(['source_proposition_id', 'target_proposition_id']);
        $outgoing = $edges->groupBy('source_proposition_id');
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->source_proposition_id][$edge->target_proposition_id] = true;
            $adjacency[$edge->target_proposition_id][$edge->source_proposition_id] = true;
        }

        $frontier = [$position->getKey()];
        $visited = [$position->getKey() => true];
        $distance = 0;
        while ($frontier !== []) {
            $ranked = [];
            $next = [];
            foreach ($frontier as $id) {
                $candidate = $propositions->get($id);
                $snapshot = $candidate->commitmentSnapshot;
                if ($distance > 0 && $candidate->tier === 'committal' && $snapshot !== null) {
                    $potential = $this->potentialFor(
                        $id, $snapshot->tension, $propositions, $outgoing,
                        $distance, $position->getKey(),
                    );
                    if ($potential > 0) {
                        $ranked[] = [
                            'proposition' => $candidate,
                            'potential_charge' => $potential,
                            'charge' => round(1 - abs($snapshot->tension), 7),
                            'distance' => $distance,
                        ];
                    }
                }
                foreach (array_keys($adjacency[$id] ?? []) as $neighbourId) {
                    if (! isset($visited[$neighbourId])) {
                        $visited[$neighbourId] = true;
                        $next[] = $neighbourId;
                    }
                }
            }
            if ($ranked !== []) {
                usort($ranked, fn (array $left, array $right): int =>
                    ($right['potential_charge'] <=> $left['potential_charge'])
                    ?: ($right['charge'] <=> $left['charge'])
                    ?: strcmp($left['proposition']->getKey(), $right['proposition']->getKey()));

                return $ranked[0];
            }
            $frontier = $next;
            $distance++;
        }

        return null;
    }

    /**
     * Potential Charge = max(0, max(|T| - |T_c|)) over measurable Expressive
     * continuations reachable before another Committal state.
     *
     * @param  Collection<string, Proposition>  $propositions
     * @param  Collection<string, Collection<int, object>>  $edges
     */
    private function potentialFor(
        string $positionId,
        float $tension,
        Collection $propositions,
        Collection $edges,
        ?int $observerDistance = null,
        ?string $excludedPositionId = null,
    ): float {
        $frontier = collect([$positionId]);
        $visited = collect([$positionId => true]);
        if ($excludedPositionId !== null) {
            $visited->put($excludedPositionId, true);
        }
        $potential = 0.0;
        $depth = 0;

        while ($frontier->isNotEmpty()) {
            $next = collect();
            $depth++;

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
                            (abs($tension) - abs($continuationTension))
                                / ($observerDistance === null ? 1 : 1 + $observerDistance + $depth),
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
