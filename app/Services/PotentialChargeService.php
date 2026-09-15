<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\CommitmentSnapshot;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Observables\LineageMeasurementService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PotentialChargeService
{
    public function __construct(
        private ProjectionService $projections,
        private PropositionMeasurementService $measurements,
    ) {}

    /**
     * Initial entry has no observer position. Rank eligible Committal states by
     * Potential, then Charge and stable ID, without an invented distance.
     *
     * @return array{proposition: Proposition, potential_charge: float, charge: float, raw_potential_charge: float, raw_charge: float, distance: int|null}
     */
    public function selectFor(Substrate $substrate): array
    {
        return $this->select($substrate) ?? throw new DomainException(
            'Potential cannot select a Gate without a Committal Proposition.'
        );
    }

    /**
     * Relative ranking utility for reachable Committal candidates. This ranking
     * does not decide whether a Participant should leave a local continuation.
     *
     * @return array{proposition: Proposition, potential_charge: float, charge: float, raw_potential_charge: float, raw_charge: float, distance: int}|null
     */
    public function selectFrom(Proposition $position): ?array
    {
        return $this->select($position->phase->cycle->participant->substrate, $position);
    }

    private function select(Substrate $substrate, ?Proposition $position = null): ?array
    {
        $snapshots = CommitmentSnapshot::query()
            ->whereBelongsTo($substrate)
            ->whereHas('proposition', fn ($query) => $query->where('tier', 'committal'))
            ->with('proposition')
            ->get();

        if ($snapshots->isEmpty()) {
            return null;
        }

        $propositions = Proposition::query()
            ->whereHas(
                'phase.cycle.participant',
                fn ($query) => $query->whereBelongsTo($substrate),
            )
            ->with('measurement')
            ->get()
            ->keyBy(fn (Proposition $proposition) => $proposition->getKey());
        $this->measurements->observeMissing($propositions->filter(fn ($item) => $item->measurement !== null));
        $edges = DB::table('supersessions')
            ->whereIn('source_proposition_id', $propositions->keys())
            ->whereIn('target_proposition_id', $propositions->keys())
            ->get(['source_proposition_id', 'target_proposition_id']);
        $outgoing = $edges->groupBy('source_proposition_id');
        $distances = $position === null ? null : $this->distancesFrom($position, $edges);

        $ranked = $snapshots
            ->filter(fn (CommitmentSnapshot $snapshot): bool => $propositions->has($snapshot->proposition_id)
                && ($distances === null || isset($distances[$snapshot->proposition_id])))
            ->map(function (CommitmentSnapshot $snapshot) use ($propositions, $outgoing, $distances): array {
                $potential = $this->potentialFor(
                    $snapshot->proposition_id,
                    $snapshot->tension,
                    $propositions,
                    $outgoing,
                );
                $charge = round(1 - abs($snapshot->tension), 7);
                $distance = $distances[$snapshot->proposition_id] ?? null;
                // Reuse substrate geometry once for observer-to-candidate distance.
                // The candidate's accumulated measurements and snapshot stay intact.
                $weight = $distance === null ? 1.0 : LineageMeasurementService::attenuation($distance);

                return [
                    'proposition' => $snapshot->proposition,
                    'potential_charge' => $potential * $weight,
                    'charge' => $charge * $weight,
                    'raw_potential_charge' => $potential,
                    'raw_charge' => $charge,
                    'distance' => $distance,
                ];
            })->all();

        usort($ranked, function (array $left, array $right): int {
            return ($right['potential_charge'] <=> $left['potential_charge'])
                ?: ($right['charge'] <=> $left['charge'])
                ?: ($left['distance'] <=> $right['distance'])
                ?: strcmp(
                    $left['proposition']->getKey(),
                    $right['proposition']->getKey(),
                );
        });

        if ($ranked === []) {
            return null;
        }

        $selected = $ranked[0];
        $selected['potential_charge'] = round($selected['potential_charge'], 7);
        $selected['charge'] = round($selected['charge'], 7);

        return $selected;
    }

    /**
     * Minimum parent/child edge distance across the substrate, including historical
     * edges and Committal boundaries. Disconnected nodes have no finite distance.
     *
     * @param  Collection<int, object>  $edges
     * @return array<string, int>
     */
    private function distancesFrom(Proposition $position, Collection $edges, bool $ancestorsOnly = false): array
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            if (! $ancestorsOnly) {
                $adjacency[$edge->source_proposition_id][$edge->target_proposition_id] = true;
            }
            $adjacency[$edge->target_proposition_id][$edge->source_proposition_id] = true;
        }
        $frontier = [$position->getKey()];
        $distances = [$position->getKey() => 0];
        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $id) {
                foreach (array_keys($adjacency[$id] ?? []) as $neighbourId) {
                    if (! isset($distances[$neighbourId])) {
                        $distances[$neighbourId] = $distances[$id] + 1;
                        $next[] = $neighbourId;
                    }
                }
            }
            $frontier = $next;
        }

        return $distances;
    }

    /**
     * Rest recovery follows every causal parent path, across Committal/Domain
     * boundaries. Compare all ancestors after observer-distance attenuation;
     * the first positive distance does not necessarily contain the winner.
     *
     * @return array{proposition: Proposition, potential_charge: float, charge: float, raw_potential_charge: float, raw_charge: float, distance: int}|null
     */
    public function recoverFrom(Proposition $position): ?array
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $propositions = Proposition::query()
            ->whereHas('phase.cycle.participant', fn ($query) => $query
                ->where('substrate_id', $substrateId))
            ->with(['measurement', 'commitmentSnapshot'])
            ->get()->keyBy('id');
        $this->measurements->observeMissing($propositions->filter(fn ($item) => $item->measurement !== null));
        $edges = DB::table('supersessions')
            ->whereIn('source_proposition_id', $propositions->keys())
            ->whereIn('target_proposition_id', $propositions->keys())
            ->get(['source_proposition_id', 'target_proposition_id']);
        $outgoing = $edges->groupBy('source_proposition_id');
        $distances = $this->distancesFrom($position, $edges, ancestorsOnly: true);
        $ranked = [];
        foreach ($distances as $id => $distance) {
            $candidate = $propositions->get($id);
            $snapshot = $candidate?->commitmentSnapshot;
            // The exhausted position is not a recovery destination, even when
            // it is itself Committal. Shared ancestors occur once at shortest distance.
            if ($distance === 0 || $candidate?->tier !== 'committal' || $snapshot === null) {
                continue;
            }
            $potential = $this->potentialFor(
                $id, $snapshot->tension, $propositions, $outgoing,
                $position->getKey(),
            );
            if ($potential <= 0) {
                continue;
            }
            $charge = round(1 - abs($snapshot->tension), 7);
            $weight = LineageMeasurementService::attenuation($distance);
            $ranked[] = [
                'proposition' => $candidate,
                'potential_charge' => $potential * $weight,
                'charge' => $charge * $weight,
                'raw_potential_charge' => $potential,
                'raw_charge' => $charge,
                'distance' => $distance,
            ];
        }

        usort($ranked, fn (array $left, array $right): int => ($right['potential_charge'] <=> $left['potential_charge'])
            ?: ($right['charge'] <=> $left['charge'])
            ?: ($left['distance'] <=> $right['distance'])
            ?: strcmp($left['proposition']->getKey(), $right['proposition']->getKey()));

        return $ranked[0] ?? null;
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
        ?string $excludedPositionId = null,
    ): float {
        return $this->observePotential($positionId, $tension, $propositions, $edges, $excludedPositionId)['value'];
    }

    /** Read-only, unattenuated Potential using exactly the recovery traversal. */
    public function observePotential(
        string $positionId,
        float $tension,
        Collection $propositions,
        Collection $edges,
        ?string $excludedPositionId = null,
    ): array {
        $frontier = collect([$positionId]);
        $visited = collect([$positionId => true]);
        if ($excludedPositionId !== null) {
            $visited->put($excludedPositionId, true);
        }
        $potential = 0.0;
        $status = 'current';

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
                        $projection = $this->projections->field(
                            new ObservableValues(
                                $measurement->correspondence,
                                $measurement->chirality,
                                $measurement->closure,
                            ),
                        );
                        $potential = max(
                            $potential,
                            $this->gain($tension, $projection['tension']),
                        );
                        if ($measurement->calculator_version !== LineageMeasurementService::VERSION && $status === 'current') {
                            $status = 'stale';
                        }
                    } else {
                        $status = 'missing';
                    }

                    $next->push($targetId);
                }
            }

            $frontier = $next;
        }

        return ['value' => round(max(0, $potential), 7), 'status' => $status];
    }

    public function gain(float $committalTension, float $expressiveTension): float
    {
        return round(max(0, abs($committalTension) - abs($expressiveTension)), 7);
    }
}
