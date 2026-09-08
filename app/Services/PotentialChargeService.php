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
     * Rank only immediate neighbours. A branch's signal is the greatest
     * distance-attenuated Potential observable through that first step.
     * The current position is excluded from each branch to avoid counting
     * a route that immediately returns through the observer.
     *
     * @return array{proposition: Proposition, potential: float}|null
     */
    public function nextFrom(Proposition $position): ?array
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

        $potentials = [];
        $best = null;
        $neighbours = array_keys($adjacency[$position->getKey()] ?? []);
        sort($neighbours, SORT_STRING);
        foreach ($neighbours as $neighbourId) {
            if ($neighbourId === $position->getKey()) {
                continue;
            }
            $visited = [$position->getKey() => true, $neighbourId => true];
            $frontier = [$neighbourId];
            $distance = 1;
            $signal = 0.0;
            while ($frontier !== []) {
                $next = [];
                foreach ($frontier as $id) {
                    $key = $id.':'.$distance;
                    if (! array_key_exists($key, $potentials)) {
                        $source = $propositions->get($id);
                        $tension = $this->tensionOf($source);
                        $potentials[$key] = $tension === null ? 0.0 : $this->potentialFor(
                            $id, $tension, $propositions, $outgoing, $distance,
                        );
                    }
                    $signal = max($signal, $potentials[$key]);
                    foreach (array_keys($adjacency[$id] ?? []) as $nextId) {
                        if (! isset($visited[$nextId])) {
                            $visited[$nextId] = true;
                            $next[] = $nextId;
                        }
                    }
                }
                $frontier = $next;
                $distance++;
            }
            if ($signal > ($best['potential'] ?? 0.0)) {
                $best = ['proposition' => $propositions->get($neighbourId), 'potential' => $signal];
            }
        }

        return $best;
    }

    private function tensionOf(Proposition $proposition): ?float
    {
        if ($proposition->commitmentSnapshot !== null) {
            return $proposition->commitmentSnapshot->tension;
        }
        $measurement = $proposition->measurement;
        if ($measurement === null || $measurement->correspondence === null
            || $measurement->chirality === null || $measurement->closure === null) {
            return null;
        }
        $projection = $this->projections->from(new ObservableValues(
            $measurement->correspondence, $measurement->chirality, $measurement->closure,
        ));

        return round($projection['prominence'] - $projection['turbulence'], 7);
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
    ): float {
        $frontier = collect([$positionId]);
        $visited = collect([$positionId => true]);
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

