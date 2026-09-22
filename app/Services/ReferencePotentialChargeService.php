<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Observables\LineageMeasurementService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Slow, explicit diagnostic oracle. Never used by participant recovery. */
final readonly class ReferencePotentialChargeService
{
    public function __construct(
        private ProjectionService $projections,
        private PropositionMeasurementService $measurements,
        private RecoveryBasinResolver $basins,
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
            ->whereHas('proposition', fn ($query) => $query->active()->where('tier', 'committal'))
            ->with('proposition')
            ->get();

        if ($snapshots->isEmpty()) {
            return null;
        }

        $propositions = Proposition::query()->active()
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
    private function distancesFrom(Proposition $position, Collection $edges): array
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge->source_proposition_id][$edge->target_proposition_id] = true;
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

    /** Backward-lineage recovery; ordinary continuation and initial entry are unchanged. */
    public function recoverFrom(Proposition $position, ?ContextHorizon $startingBasin = null, ?Participant $observer = null): ?array
    {
        return $this->inspectRecoveryFrom($position, $startingBasin, $observer)['selection'];
    }

    /** Read-only diagnostics share exactly the selection path used by Cycle entry. */
    public function inspectRecoveryFrom(Proposition $position, ?ContextHorizon $startingBasin = null, ?Participant $observer = null): array
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        // A retained boundary belongs to its creating Domain but enters its child.
        // Callers with a completed Cycle supply that Cycle's durable Horizon.
        $startingBasin ??= $position->tier === 'committal' && $position->commitmentSnapshot !== null
            ? app(ContextHorizonProjector::class)->forEntry($position)
            : $position->phase->cycle->contextHorizon;
        if ($startingBasin->substrate_id !== $substrateId) {
            throw new DomainException('Recovery cannot cross substrates.');
        }
        app(CausalContextService::class)->assertGate($position, $startingBasin);
        $horizons = ContextHorizon::where('substrate_id', $substrateId)->get()->keyBy('id');
        $opened = $horizons->whereNotNull('opened_by_proposition_id')->keyBy('opened_by_proposition_id');
        $propositions = Proposition::query()->active()
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $substrateId))
            ->with(['measurement', 'commitmentSnapshot', 'phase.cycle'])
            ->get()->keyBy('id');
        $historicalIds = Proposition::whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $substrateId))->select('id');
        $edges = DB::table('supersessions')
            ->whereIn('source_proposition_id', $historicalIds)
            ->whereIn('target_proposition_id', $historicalIds)
            ->get(['source_proposition_id', 'target_proposition_id', 'type']);
        $outgoing = $edges->groupBy('source_proposition_id');
        $incoming = $edges->groupBy('target_proposition_id');
        $distances = app(BackwardRecoveryLineage::class)->distances($position, $observer,
            fn (string $id, string $horizonId) => $incoming->get($id, collect())->pluck('source_proposition_id')->all());
        $members = $propositions->groupBy(fn (Proposition $node) => $node->phase->cycle->context_horizon_id);
        $entries = $propositions->filter(fn (Proposition $node) => $node->tier === 'committal'
            && $node->commitmentSnapshot?->substrate_id === $substrateId)
            ->groupBy(function (Proposition $node) use ($opened): string {
                // A boundary's entry belongs exclusively to its direct child.
                return $node->commitmentSnapshot->isClosureBoundary()
                    ? ($opened->get($node->id)?->id ?? '')
                    : $node->phase->cycle->context_horizon_id;
            });
        $excluded = app(ExhaustionService::class)->unavailable($position) ? $position->id : null;
        $result = $this->basins->resolve($startingBasin, $horizons,
            function (ContextHorizon $basin) use ($excluded, $members, $entries, $outgoing, $distances): Collection {
                $candidates = $entries->get($basin->id, collect())
                    ->filter(fn (Proposition $node) => $node->id !== $excluded && isset($distances[$node->id]))
                    ->sortBy(fn (Proposition $node) => $distances[$node->id])->take(1);
                if ($candidates->isEmpty()) {
                    return collect();
                }
                // Select the nearest eligible Committal before observing values.
                // Attenuated values below are diagnostics only. A boundary seed is added
                // as provenance; expressive evidence comes only from this Domain.
                $local = $members->get($basin->id, collect())->keyBy('id')->union($candidates->keyBy('id'));
                $this->measurements->observeMissing($local->filter(fn ($node) => $node->measurement !== null));
                $selected = collect();
                foreach ($candidates as $candidate) {
                    $snapshot = $candidate->commitmentSnapshot;
                    $potential = $this->potentialFor($candidate->id, $snapshot->tension, $local, $outgoing, $excluded);
                    $charge = round(1 - abs($snapshot->tension), 7);
                    $distance = $distances[$candidate->id];
                    $weight = LineageMeasurementService::attenuation($distance);
                    $selected->push([
                        'proposition' => $candidate,
                        'potential_charge' => $potential * $weight,
                        'charge' => $charge * $weight,
                        'raw_potential_charge' => $potential,
                        'raw_charge' => $charge,
                        'distance' => $distance,
                        'context_horizon_id' => $basin->id,
                    ]);
                }

                return $selected;
            }, fn (ContextHorizon $basin): bool => isset($distances[$basin->opened_by_proposition_id]),
            fn (ContextHorizon $basin): bool => app(ExhaustionService::class)->boundaryExhausted($basin));
        $candidates = $result['candidates']->all();

        return [
            'selection' => $candidates[0] ?? null,
            'starting_context_horizon_id' => $startingBasin->id,
            'searched_basins' => $result['searched_basins'],
            'fallback_depth' => $result['fallback_depth'],
            'eligible_candidate_ids' => array_map(fn (array $candidate) => $candidate['proposition']->id, $candidates),
        ];
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
        $seeds = app(CommitRegionService::class)->seedIdsFor($propositions, $propositions);
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

                    if ($target === null || $target->tier === 'committal' || ($seeds[$targetId] ?? null) !== $positionId) {
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

