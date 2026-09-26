<?php

namespace App\Services;

use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Proposition;
use DomainException;

final readonly class ProjectedRecoveryService
{
    public function __construct(private PotentialProjectionService $projection, private PotentialRegionCalculator $calculator,
        private BackwardRecoveryLineage $lineage, private RecoveryBasinResolver $basins) {}

    public function inspect(Proposition $position, ?ContextHorizon $start = null, ?Participant $observer = null): array
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $start ??= $position->tier === 'committal' && $position->commitmentSnapshot !== null
            ? app(ContextHorizonProjector::class)->forEntry($position) : $position->phase->cycle->contextHorizon;
        if ($start->substrate_id !== $substrateId) {
            throw new DomainException('Recovery cannot cross substrates.');
        }
        app(CausalContextService::class)->assertGate($position, $start);
        $version = $this->projection->assertCurrent($substrateId);
        $locals = $parents = [];
        $localFor = function (string $id) use (&$locals): array {
            return $locals[$id] ??= $this->projection->region($id)['local'];
        };
        $distances = $this->lineage->distances($position, $observer,
            function (string $id, string $horizonId) use ($localFor, &$parents): array {
                if (! isset($parents[$horizonId])) {
                    $parents[$horizonId] = [];
                    foreach ($localFor($horizonId)['edges'] as [$source, $target]) {
                        $parents[$horizonId][$target][] = $source;
                    }
                }

                return $parents[$horizonId][$id] ?? [];
            });
        $horizons = collect();
        $horizon = $start;
        while ($horizon !== null && ! $horizons->has($horizon->id)) {
            $horizons->put($horizon->id, $horizon);
            $horizon = $horizon->parent_context_horizon_id === null ? null : ContextHorizon::find($horizon->parent_context_horizon_id);
        }
        $unavailable = app(ExhaustionService::class)->unavailable($position);
        $excluded = $unavailable ? $position->id : null;
        $crossover = null;
        $skip = null;
        $rested = $observer?->cycles()->latest('sequence')->first();
        if ($rested?->rested_at !== null && $rested->completed_at !== null
            && $observer->current_proposition_id === $position->id
            && $rested->context_horizon_id === $start->id) {
            $crossover = app(CrossoverMarginService::class)->inspect($rested);
            $seed = Proposition::find($rested->entry_committal_id);
            // Origin remains the final fallback, even when its margin is closed.
            if ($crossover['closed'] && $seed?->phase->cycle->entry_committal_id !== null
                && $seed->commitmentSnapshot?->calculator_version !== 'origin-bootstrap-v1') {
                $skip = $rested->entry_committal_id;
            }
        }
        $result = $this->basins->resolve($start, $horizons,
            function (ContextHorizon $basin) use ($localFor, $distances, $excluded, $skip) {
                $local = $localFor($basin->id);
                // The backward walk is in causal order. An open Committal is a
                // recovery boundary even at zero Potential; older values cannot
                // compete. Entries already exclude explicitly exhausted basins.
                foreach ($distances as $id => $distance) {
                    if ($id !== $excluded && $id !== $skip && isset($local['entries'][$id])) {
                        return collect($this->calculator->candidates($local, $excluded, lineage: [$id => $distance]))
                            // Retain numerical diagnostics, never use them to select.
                            ->map(fn ($entry) => $this->calculator->attenuate($entry, $distance));
                    }
                }

                return collect();
            }, fn (ContextHorizon $basin) => isset($distances[$basin->opened_by_proposition_id]),
            // This Rest may pass its skipped boundary governor on the recorded
            // backward path. It does not close the Horizon or any shared Region.
            fn (ContextHorizon $basin) => app(ExhaustionService::class)->boundaryExhausted($basin)
                || ($skip !== null && $basin->opened_by_proposition_id === $skip));
        $candidates = $result['candidates']->all();
        $selection = $candidates[0] ?? null;
        if ($selection !== null) {
            $selection['proposition'] = Proposition::findOrFail($selection['proposition_id']);
            $selection['reason'] = $selection['proposition']->commitmentSnapshot?->calculator_version === 'origin-bootstrap-v1' && $unavailable ? 'origin_renewal' : 'open_basin';
        }
        if ($version !== $this->projection->assertCurrent($substrateId)) {
            throw new PotentialProjectionUnavailable($substrateId);
        }

        return ['selection' => $selection, 'starting_context_horizon_id' => $start->id,
            'searched_basins' => $result['searched_basins'], 'fallback_depth' => $result['fallback_depth'],
            'eligible_candidate_ids' => array_column($candidates, 'proposition_id'),
            'projected_field_version' => $version, 'recovery_hops' => $result['fallback_depth'],
            'crossover' => $crossover, 'rest_skipped_committal_id' => $skip];
    }
}
