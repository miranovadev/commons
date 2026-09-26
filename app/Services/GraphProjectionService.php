<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Enums\PhaseKind;
use App\Exceptions\PotentialProjectionUnavailable;
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
    ) {}

    public function load(Substrate $substrate, Collection $visible, bool $historical = false): void
    {
        if ($visible->isEmpty()) {
            return;
        }
        // Observer mode changes visibility only. Every view reads the same field.
        $this->loadActive($substrate, $visible, $historical);
        $this->loadHistoricalMarkers($substrate, $visible);
    }

    /** Accepted contribution history only; never re-run today's Bias population or field. */
    private function loadHistoricalMarkers(Substrate $substrate, Collection $visible): void
    {
        $ids = $visible->pluck('id');
        $tragedies = DB::table('tragedies as tragedy')
            ->join('context_horizons as horizon', 'horizon.id', '=', 'tragedy.context_horizon_id')
            ->where('horizon.substrate_id', $substrate->id)->whereIn('tragedy.terminal_proposition_id', $ids)
            ->selectRaw('tragedy.terminal_proposition_id as id, NULL as before, NULL as after, ? as event_type', ['tragedy']);
        // A consumed owning-Region Tragedy warrant on a foreign Bias classification
        // records the successful escape. Ordinary/local/mediated classifications
        // use ordinary Receipts and therefore cannot satisfy this historical proof.
        $renewals = DB::table('receipt_consumptions as consumption')
            ->join('receipts as receipt', 'receipt.id', '=', 'consumption.receipt_id')
            ->join('tragedies as tragedy', 'tragedy.receipt_id', '=', 'receipt.id')
            ->join('propositions as target', 'target.id', '=', 'consumption.proposition_id')
            ->join('phases as phase', 'phase.id', '=', 'target.phase_id')
            ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
            ->join('participants as actor', 'actor.id', '=', 'cycle.participant_id')
            ->join('propositions as source', 'source.id', '=', 'consumption.source_proposition_id')
            ->join('phases as producer', 'producer.id', '=', 'source.phase_id')
            ->join('cycles as source_cycle', 'source_cycle.id', '=', 'producer.cycle_id')
            ->join('supersessions as edge', fn ($join) => $join
                ->on('edge.target_proposition_id', '=', 'target.id')->on('edge.source_proposition_id', '=', 'source.id')
                ->on('edge.phase_id', '=', 'phase.id')->where('edge.type', 'reclassify'))
            ->join('reclassification_results as result', fn ($join) => $join
                ->on('result.target_proposition_id', '=', 'target.id')->on('result.source_proposition_id', '=', 'source.id')
                ->on('result.register', '=', 'target.register'))
            ->whereIn('target.id', $ids)->where('actor.substrate_id', $substrate->id)
            ->where('receipt.type', 'tragedy')->where('phase.kind', PhaseKind::Bias->value)
            ->where('producer.kind', PhaseKind::Mediate->value)
            ->where('target.tier', 'expressive')->where('source.tier', 'expressive')
            ->whereColumn('consumption.phase_id', 'phase.id')
            ->whereColumn('tragedy.governing_committal_id', 'cycle.entry_committal_id')
            ->whereColumn('tragedy.context_horizon_id', 'cycle.context_horizon_id')
            ->whereColumn('source_cycle.entry_committal_id', '!=', 'cycle.entry_committal_id')
            ->distinct()->selectRaw('target.id, NULL as before, NULL as after, ? as event_type', ['renewal']);
        app(PropositionInspectionService::class)->loadMarkers($substrate, $visible, $tragedies->unionAll($renewals));
    }

    /** Active cards read maintained region state, never the whole substrate. */
    private function loadActive(Substrate $substrate, Collection $visible, bool $historical): void
    {
        $current = true;
        try {
            $version = app(PotentialProjectionService::class)->assertCurrent($substrate->id);
        } catch (PotentialProjectionUnavailable) {
            $current = false;
        }
        // Identity is immutable governance even when disposable terrain is stale.
        $seeds = app(CommitRegionService::class)->seedIdsFor($visible, $visible);
        $snapshots = CommitmentSnapshot::whereIn('proposition_id', collect($seeds)->values()->filter()->merge($visible->pluck('id'))->unique())->get()->keyBy('proposition_id');
        $entries = DB::table('potential_entries')->whereIn('proposition_id', $visible->pluck('id'))->get()->keyBy('proposition_id');
        $touches = Phase::whereIn('gate_proposition_id', $visible->pluck('id'))->selectRaw('gate_proposition_id, count(*) as aggregate')
            ->groupBy('gate_proposition_id')->pluck('aggregate', 'gate_proposition_id');
        if ($current) {
            try {
                $current = $version === app(PotentialProjectionService::class)->assertCurrent($substrate->id);
            } catch (PotentialProjectionUnavailable) {
                $current = false;
            }
        }
        $activeIds = $historical ? Proposition::active()->whereKey($visible->pluck('id'))->pluck('id')->flip() : $visible->pluck('id')->flip();
        $exhaustion = $historical ? app(ExhaustionService::class)->withHistoricalState(Proposition::whereKey($visible->pluck('id')))->get()->keyBy('id') : collect();
        foreach ($visible as $node) {
            $state = $exhaustion->get($node->id);
            $node->setAttribute('exhaustion', ['exhausted' => $state?->exhaustion_kind === 'excursion',
                'basin_exhausted' => $state?->exhaustion_kind === 'basin', 'exhausted_at' => $state?->exhausted_at]);
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
            $node->setAttribute('active', $activeIds->has($node->id));
            if (! $activeIds->has($node->id) && $current) {
                $potential = 0.0;
                $potentialStatus = 'current';
            }
            $node->setAttribute('participation_count', (int) $touches->get($node->id, 0));
            $node->setAttribute('field', [...$field, 'status' => $status,
                'source' => $snapshot !== null ? 'commitment_snapshot' : 'measurement',
                'potential_charge' => $potential, 'potential_status' => $potentialStatus,
                'potential_basis' => $node->tier === 'committal' ? 'region' : 'proposition',
                'committal_proposition_id' => $seed?->proposition_id]);
        }
    }
}

