<?php

namespace App\Services;

use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Proposition;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ProjectedRecoveryService
{
    public function __construct(private PotentialProjectionService $projection, private PotentialRegionCalculator $calculator) {}

    public function inspect(Proposition $position, ?ContextHorizon $start = null): array
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $start ??= $position->tier === 'committal' && $position->commitmentSnapshot !== null
            ? app(ContextHorizonProjector::class)->forEntry($position) : $position->phase->cycle->contextHorizon;
        if ($start->substrate_id !== $substrateId) {
            throw new DomainException('Recovery cannot cross substrates.');
        }
        app(CausalContextService::class)->assertGate($position, $start);
        $version = $this->projection->assertCurrent($substrateId);
        $id = $start->id;
        $from = $position->id;
        $offset = $depth = 0;
        $visited = $searched = [];
        $ranked = [];
        while (true) {
            if (isset($visited[$id])) {
                throw new DomainException('Recovery requires acyclic Context Horizon provenance.');
            }
            $region = $this->projection->region($id);
            $local = $region['local'];
            $distances = $this->calculator->distances($local, $from);
            $ranked = $this->frontierFrom($local, $distances, $offset, $position->id, $visited, $searched);
            $visited[$id] = true;
            if ($ranked !== [] || $local['parent'] === null) {
                break;
            }
            if (! isset($distances[$local['opener']])) {
                break; // Disconnected provenance is not an invented distance.
            }
            $offset += $distances[$local['opener']];
            $from = $local['opener'];
            $id = $local['parent'];
            $depth++;
        }
        if ($ranked === [] && $this->renewable($position)) {
            $origin = CommitmentSnapshot::where('substrate_id', $substrateId)->where('calculator_version', 'origin-bootstrap-v1')->firstOrFail();
            $entry = DB::table('potential_entries')->where('proposition_id', $origin->proposition_id)->first();
            $distance = $distances[$origin->proposition_id] ?? null;
            if ($entry === null || $distance === null) {
                throw new PotentialProjectionUnavailable($substrateId);
            }
            $ranked[] = [...$this->calculator->attenuate([
                'proposition_id' => $origin->proposition_id, 'context_horizon_id' => $entry->context_horizon_id,
                'raw_potential_charge' => (float) $entry->raw_potential_charge, 'raw_charge' => (float) $entry->raw_charge,
            ], $offset + $distance), 'reason' => 'origin_renewal'];
        }
        $ranked = $this->calculator->rank($ranked);
        $selection = $ranked[0] ?? null;
        if ($selection !== null) {
            $selection['proposition'] = Proposition::findOrFail($selection['proposition_id']);
        }
        if ($version !== $this->projection->assertCurrent($substrateId)) {
            throw new PotentialProjectionUnavailable($substrateId);
        }

        return ['selection' => $selection, 'starting_context_horizon_id' => $start->id,
            'searched_basins' => $searched, 'fallback_depth' => $depth,
            'eligible_candidate_ids' => array_column($ranked, 'proposition_id'),
            'projected_field_version' => $version, 'recovery_hops' => $depth];
    }

    private function frontierFrom(array $local, array $distances, int $offset, string $excluded, array $visited, array &$searched): array
    {
        $ranked = [];
        $children = DB::table('context_horizons as horizon')->leftJoin('potential_region_states as projection',
            'projection.context_horizon_id', '=', 'horizon.id')->where('horizon.parent_context_horizon_id', $local['id'])
            ->get(['horizon.id', 'horizon.opened_by_proposition_id', 'projection.frontier']);
        foreach ($children as $child) {
            if (isset($visited[$child->id]) || ! isset($distances[$child->opened_by_proposition_id])) {
                continue;
            }
            if ($child->frontier === null) {
                throw new PotentialProjectionUnavailable(ContextHorizon::findOrFail($local['id'])->substrate_id);
            }
            $distance = $offset + $distances[$child->opened_by_proposition_id];
            if ($excluded === $child->opened_by_proposition_id) {
                $state = $this->projection->region($child->id)['local'];
                $ranked = array_merge($ranked, $this->frontierFrom($state,
                    $this->calculator->distances($state, $excluded), $distance, $excluded, $visited, $searched));
                continue;
            }
            $frontier = json_decode($child->frontier, true, flags: JSON_THROW_ON_ERROR);
            foreach ($frontier as $entry) {
                $ranked[] = $this->calculator->attenuate($entry, $distance + $entry['distance']);
            }
            foreach (collect($frontier)->groupBy('context_horizon_id') as $basinId => $entries) {
                $searched[] = ['context_horizon_id' => $basinId, 'eligible_candidates' => $entries->count()];
            }
        }
        if ($ranked === []) {
            foreach ($this->calculator->candidates($local, $excluded) as $entry) {
                if ($entry['raw_potential_charge'] > 0 && isset($distances[$entry['proposition_id']])) {
                    $ranked[] = $this->calculator->attenuate($entry, $offset + $distances[$entry['proposition_id']]);
                }
            }
            $searched[] = ['context_horizon_id' => $local['id'], 'domain' => $local['domain'], 'eligible_candidates' => count($ranked)];
        }

        return $ranked;
    }

    private function renewable(Proposition $position): bool
    {
        return app(OriginBranchService::class)->pruned($position)
            || ($position->commitmentSnapshot?->calculator_version === 'origin-bootstrap-v1'
                && DB::table('origin_branches')->where('origin_proposition_id', $position->id)->whereNotNull('pruned_at')->exists());
    }
}
