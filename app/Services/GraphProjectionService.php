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

    public function load(Substrate $substrate, Collection $visible, bool $historical = true): void
    {
        if ($visible->isEmpty()) {
            return;
        }
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

        $activeIds = Proposition::active()->whereKey($visible->pluck('id'))->pluck('id')->flip();
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
}
