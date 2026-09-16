<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\CommitmentSnapshot;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Observables\LineageMeasurementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Human observation only. Never repairs measurements or ranks a Participant's next move. */
final readonly class GraphProjectionService
{
    public function __construct(
        private ProjectionService $projections,
        private PotentialChargeService $potential,
        private CommitRegionService $regions,
    ) {}

    public function load(Substrate $substrate, Collection $visible, bool $historical = false): void
    {
        if ($visible->isEmpty()) {
            return;
        }
        if (! $historical) {
            $this->loadActive($substrate, $visible);
            return;
        }
        // Explicit historical inspection retains ledger geometry.
        // Include hidden history/continuations: the 500-card display limit must
        // not change the canonical value of a visible proposition.
        $nodes = Proposition::query()->when(! $historical, fn ($query) => $query->active())->whereHas('phase.cycle.participant', fn ($q) => $q->whereBelongsTo($substrate))
            ->select(['id', 'tier'])->with('measurement')->get()->keyBy('id');
        $edges = DB::table('supersessions')->whereIn('source_proposition_id', $nodes->keys())
            ->whereIn('target_proposition_id', $nodes->keys())->get(['source_proposition_id', 'target_proposition_id']);
        $snapshots = CommitmentSnapshot::whereBelongsTo($substrate)->get()->keyBy('proposition_id');
        $touches = Phase::whereHas('cycle.participant', fn ($q) => $q->whereBelongsTo($substrate))
            ->whereIn('gate_proposition_id', $visible->pluck('id'))->selectRaw('gate_proposition_id, count(*) as aggregate')
            ->groupBy('gate_proposition_id')->pluck('aggregate', 'gate_proposition_id');
        $seeds = $this->regions->seedIdsFor($visible, $nodes, $edges->groupBy('target_proposition_id'));
        $outgoing = $edges->groupBy('source_proposition_id');

        $activeIds = $historical
            ? Proposition::active()->whereKey($visible->pluck('id'))->pluck('id')->flip()
            : $nodes->keys()->flip();
        foreach ($visible as $proposition) {
            $proposition->setAttribute('active', $activeIds->has($proposition->id));
            $measurement = $nodes->get($proposition->id)?->measurement;
            $snapshot = $proposition->tier === 'committal' ? $snapshots->get($proposition->id) : null;
            $source = $snapshot ?? $measurement;
            $complete = $source !== null && $source->correspondence !== null && $source->chirality !== null && $source->closure !== null;
            $status = ! $complete ? 'missing' : ($snapshot !== null || $measurement->calculator_version === LineageMeasurementService::VERSION ? 'current' : 'stale');
            $field = $complete ? $this->projections->field(new ObservableValues($source->correspondence, $source->chirality, $source->closure)) : ['tension' => null, 'charge' => null];
            if ($snapshot !== null) {
                $field = ['tension' => $snapshot->tension, 'charge' => round(1 - abs($snapshot->tension), 7)];
            }
            $seed = $snapshots->get($seeds[$proposition->id]);
            $potentialStatus = $status;
            $potential = null;
            if ($snapshot !== null) {
                $observation = $this->potential->observePotential($proposition->id, $snapshot->tension, $nodes, $outgoing);
                $potentialStatus = $observation['status'];
                $potential = $potentialStatus === 'missing' ? null : $observation['value'];
            } elseif ($complete && $seed !== null && $proposition->tier === 'expressive') {
                $potential = $this->potential->gain($seed->tension, $field['tension']);
            } else {
                $potentialStatus = 'missing';
            }
            $proposition->setAttribute('participation_count', (int) $touches->get($proposition->id, 0));
            $proposition->setAttribute('field', [
                ...$field, 'status' => $status, 'source' => $snapshot !== null ? 'commitment_snapshot' : 'measurement',
                'potential_charge' => $potential, 'potential_status' => $potentialStatus,
                'potential_basis' => $proposition->tier === 'committal' ? 'region' : 'proposition',
                'committal_proposition_id' => $seed?->proposition_id,
            ]);
        }
    }
    /** Active cards read maintained region state, never the whole substrate. */
    private function loadActive(Substrate $substrate, Collection $visible): void
    {
        $current = true;
        try {
            $version = app(PotentialProjectionService::class)->assertCurrent($substrate->id);
        } catch (\App\Exceptions\PotentialProjectionUnavailable) {
            $current = false;
        }
        $horizonIds = $visible->map(fn ($node) => $node->phase->cycle->context_horizon_id)->unique();
        $regions = DB::table('potential_region_states')->whereIn('context_horizon_id', $horizonIds)->pluck('local_state');
        $nodes = [];
        foreach ($regions as $json) {
            $nodes += json_decode($json, true, flags: JSON_THROW_ON_ERROR)['nodes'];
        }
        $seeds = $visible->mapWithKeys(fn ($node) => [$node->id => $node->tier === 'committal' ? $node->id : ($nodes[$node->id]['seed_id'] ?? null)]);
        $snapshots = CommitmentSnapshot::whereIn('proposition_id', $seeds->values()->filter()->merge($visible->pluck('id'))->unique())->get()->keyBy('proposition_id');
        $entries = DB::table('potential_entries')->whereIn('proposition_id', $visible->pluck('id'))->get()->keyBy('proposition_id');
        $touches = Phase::whereIn('gate_proposition_id', $visible->pluck('id'))->selectRaw('gate_proposition_id, count(*) as aggregate')
            ->groupBy('gate_proposition_id')->pluck('aggregate', 'gate_proposition_id');
        if ($current) {
            try {
                $current = $version === app(PotentialProjectionService::class)->assertCurrent($substrate->id);
            } catch (\App\Exceptions\PotentialProjectionUnavailable) {
                $current = false;
            }
        }
        foreach ($visible as $node) {
            $measurement = $node->measurement;
            $snapshot = $node->tier === 'committal' ? $snapshots->get($node->id) : null;
            $source = $snapshot ?? $measurement;
            $complete = $source !== null && $source->correspondence !== null && $source->chirality !== null && $source->closure !== null;
            $status = ! $complete ? 'missing' : ($snapshot !== null || ($current && $measurement->calculator_version === LineageMeasurementService::VERSION) ? 'current' : 'stale');
            $field = $complete ? $this->projections->field(new ObservableValues($source->correspondence, $source->chirality, $source->closure))
                : ['tension' => null, 'charge' => null];
            if ($snapshot !== null) {
                $field = ['tension' => $snapshot->tension, 'charge' => round(1 - abs($snapshot->tension), 7)];
            }
            $seed = $snapshots->get($seeds[$node->id]);
            $potential = null;
            $potentialStatus = $current ? 'missing' : 'stale';
            if ($current && $snapshot !== null && $entries->has($node->id) && $entries[$node->id]->complete) {
                $potential = (float) $entries[$node->id]->raw_potential_charge;
                $potentialStatus = 'current';
            } elseif ($current && $complete && $seed !== null && $node->tier === 'expressive') {
                $potential = $this->potential->gain($seed->tension, $field['tension']);
                $potentialStatus = $status;
            }
            $node->setAttribute('active', true);
            $node->setAttribute('participation_count', (int) $touches->get($node->id, 0));
            $node->setAttribute('field', [...$field, 'status' => $status,
                'source' => $snapshot !== null ? 'commitment_snapshot' : 'measurement',
                'potential_charge' => $potential, 'potential_status' => $potentialStatus,
                'potential_basis' => $node->tier === 'committal' ? 'region' : 'proposition',
                'committal_proposition_id' => $seed?->proposition_id]);
        }
    }

}
