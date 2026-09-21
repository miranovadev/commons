<?php

namespace App\Services;

use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PotentialChargeService
{
    public function __construct(private PotentialProjectionService $projection, private ProjectedRecoveryService $recovery) {}

    /** Numerical capacity in this Horizon, independent of recovery selection and frontier ranking. */
    public function hasRemainingInHorizon(Phase $phase): bool
    {
        $this->projection->assertCurrent($phase->cycle->participant->substrate_id);
        $local = $this->projection->region($phase->cycle->context_horizon_id)['local'];
        foreach (app(ExhaustedContinuationService::class)->ids($phase)->keys() as $id) {
            if (isset($local['nodes'][$id])) {
                $local['nodes'][$id]['active'] = false;
            }
        }

        // Include every local Committal: a later successful Commit cannot hide
        // capacity at an earlier position. Child Horizons do not supply this
        // Horizon's capacity. Spentness and action schemas remain separate.
        return collect(app(PotentialRegionCalculator::class)->candidates($local, shadowCompletedRegions: false))
            ->contains(fn ($entry) => $entry['raw_potential_charge'] > 0.00000005);
    }

    /** Existing regional algebra; only the exhaustion/final-attempt qualification scope changes. */
    public function hasRemainingInRegion(Phase $phase): bool
    {
        $seed = app(ExhaustionService::class)->regionFor($phase);
        if ($seed === null) {
            return true; // Ambiguous membership supplies no local exhaustion proof.
        }
        $this->projection->assertCurrent($phase->cycle->participant->substrate_id);
        $local = $this->projection->region($phase->cycle->context_horizon_id)['local'];
        foreach (app(ExhaustedContinuationService::class)->ids($phase)->keys() as $id) {
            if (isset($local['nodes'][$id])) {
                $local['nodes'][$id]['active'] = false;
            }
        }

        return collect(app(PotentialRegionCalculator::class)->candidates($local, lineage: [$seed->id => 0]))
            ->contains(fn ($entry) => $entry['raw_potential_charge'] > 0.00000005);
    }

    public function selectFor(Substrate $substrate): array
    {
        $version = $this->projection->assertCurrent($substrate->id);
        $entry = DB::table('potential_entries')->where('substrate_id', $substrate->id)
            ->orderByDesc('raw_potential_charge')->orderByDesc('raw_charge')->orderBy('proposition_id')->first();
        if ($entry === null) {
            throw new DomainException('Potential cannot select a Gate without a Committal Proposition.');
        }
        $selection = app(PotentialRegionCalculator::class)->attenuate([
            'proposition_id' => $entry->proposition_id, 'raw_potential_charge' => (float) $entry->raw_potential_charge,
            'raw_charge' => (float) $entry->raw_charge,
        ], null);
        $selection['proposition'] = Proposition::findOrFail($entry->proposition_id);
        if ($version !== $this->projection->assertCurrent($substrate->id)) {
            throw new PotentialProjectionUnavailable($substrate->id);
        }

        return $selection;
    }

    public function selectFrom(Proposition $position): ?array
    {
        // Legacy relative-ranking diagnostic (no production callers). Recovery
        // stops at the nearest eligible Committal on the actual backward lineage.
        return app(ReferencePotentialChargeService::class)->selectFrom($position);
    }

    public function recoverFrom(Proposition $position, ?ContextHorizon $startingBasin = null, ?Participant $observer = null): ?array
    {
        return $this->inspectRecoveryFrom($position, $startingBasin, $observer)['selection'];
    }

    public function inspectRecoveryFrom(Proposition $position, ?ContextHorizon $startingBasin = null, ?Participant $observer = null): array
    {
        return $this->recovery->inspect($position, $startingBasin, $observer);
    }

    /** Explicit reference calculation for maintenance tools and semantic comparisons. */
    public function observePotential(string $positionId, float $tension, Collection $propositions, Collection $edges, ?string $excludedPositionId = null): array
    {
        return app(ReferencePotentialChargeService::class)->observePotential($positionId, $tension, $propositions, $edges, $excludedPositionId);
    }

    public function gain(float $committalTension, float $expressiveTension): float
    {
        return round(max(0, abs($committalTension) - abs($expressiveTension)), 7);
    }
}
