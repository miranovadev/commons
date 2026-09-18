<?php

namespace App\Services;

use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PotentialChargeService
{
    public function __construct(private PotentialProjectionService $projection, private ProjectedRecoveryService $recovery) {}

    /** Maintained Horizon-local projection; never derive the whole substrate. */
    public function hasRemainingInHorizon(Phase $phase): bool
    {
        $this->projection->assertCurrent($phase->cycle->participant->substrate_id);
        $horizonId = $phase->cycle->context_horizon_id;
        if (DB::table('potential_entries')->where('context_horizon_id', $horizonId)
            ->where('raw_potential_charge', '>', 0.00000005)->exists()) {
            return true;
        }
        $frontier = json_decode(DB::table('potential_region_states')->where('context_horizon_id', $horizonId)
            ->value('frontier') ?? '[]', true, flags: JSON_THROW_ON_ERROR);

        return collect($frontier)->contains(fn ($entry) => $entry['raw_potential_charge'] > 0.00000005);
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
        // has a different contract: it excludes the exhausted observer path.
        return app(ReferencePotentialChargeService::class)->selectFrom($position);
    }

    public function recoverFrom(Proposition $position, ?ContextHorizon $startingBasin = null): ?array
    {
        return $this->inspectRecoveryFrom($position, $startingBasin)['selection'];
    }

    public function inspectRecoveryFrom(Proposition $position, ?ContextHorizon $startingBasin = null): array
    {
        return $this->recovery->inspect($position, $startingBasin);
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
