<?php

namespace Tests\Feature;

use App\Domain\Observables\LocalMeasurements;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\CommitmentSnapshot;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\LedgerService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\PropositionMeasurementService;
use App\Services\PotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineageAttenuationTest extends TestCase
{
    use RefreshDatabase;

    private Phase $phase;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'attenuation', 'title' => 'Attenuation', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1])->save();
        $this->phase = new Phase;
        $this->phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order, 'write_register' => 'canon', 'started_at' => now()])->save();
    }

    public function test_inverse_square_geometry_and_neutral_positions_apply_to_every_component(): void
    {
        $provenance = $this->node('provenance', 'committal');
        $middle = $this->node('middle');
        $head = $this->node('head');
        $this->edge($provenance, $middle);
        $this->edge($middle, $head);
        foreach ([1, 0.25, 1 / 9, 0.0625, 0.04] as $d => $weight) {
            $this->assertEqualsWithDelta($weight, LineageMeasurementService::attenuation($d), 1e-12);
        }
        $mass = 1 + 1 / 4 + 1 / 9;
        foreach ([[$head, 0], [$middle, 1], [$provenance, 2]] as [$signal, $distance]) {
            $this->rawValues([$signal->id => 1]);
            $result = app(LineageMeasurementService::class)->calculate($head);
            $expected = round(LineageMeasurementService::attenuation($distance) / $mass, 7);
            foreach (['correspondence', 'chirality', 'depth', 'cadence'] as $component) {
                $this->assertSame($expected, $result->{$component});
            }
            $this->assertSame($expected, $result->observables()->closure);
        }
    }

    public function test_unanimity_and_signed_cancellation_need_no_clamping(): void
    {
        $parent = $this->node('parent', 'committal');
        $head = $this->node('head');
        $this->edge($parent, $head);
        foreach ([-1, 1] as $sign) {
            $this->rawValues([$parent->id => $sign, $head->id => $sign]);
            $this->assertSame((float) $sign, app(LineageMeasurementService::class)->calculate($head)->chirality);
        }
        $this->rawValues([$parent->id => -1, $head->id => 1]);
        $this->assertSame(0.6, app(LineageMeasurementService::class)->calculate($head)->chirality);
        $this->rawValues([$parent->id => 1, $head->id => -1]);
        $this->assertSame(-0.6, app(LineageMeasurementService::class)->calculate($head)->chirality);
    }

    public function test_shared_ancestors_count_once_at_shortest_distance_and_stop_at_provenance(): void
    {
        $beyond = $this->node('beyond');
        $provenance = $this->node('provenance', 'committal');
        $left = $this->node('left');
        $right = $this->node('right');
        $head = $this->node('head');
        foreach ([[$beyond, $provenance], [$provenance, $left], [$provenance, $right], [$left, $head], [$right, $head], [$provenance, $head]] as [$from, $to]) {
            $this->edge($from, $to);
        }
        $this->rawValues([$provenance->id => 1]);
        $service = app(LineageMeasurementService::class);
        $positions = $service->positions($head);
        $this->assertCount(4, $positions);
        $this->assertNotContains($beyond->id, $positions->map(fn ($row) => $row[0]->id)->all());
        $this->assertSame(1, $positions->first(fn ($row) => $row[0]->id === $provenance->id)[1]);
        $this->assertSame(round(0.25 / 1.75, 7), $service->calculate($head)->chirality);
    }

    public function test_raw_calculation_never_reads_or_reuses_accumulated_projections(): void
    {
        $parent = $this->node('parent', 'committal');
        $head = $this->node('head');
        $this->edge($parent, $head);
        $raw = app(LocalMeasurementService::class);
        $before = $raw->calculate($head);
        app(PropositionMeasurementService::class)->project($parent, 1, 1, 1);
        app(PropositionMeasurementService::class)->project($head, 1, 1, 1);
        $this->assertEquals($before, $raw->calculate($head));
        $this->assertSame(-1.0, app(LineageMeasurementService::class)->calculate($head)->chirality);
    }

    public function test_preview_and_actual_committal_use_identical_distances(): void
    {
        $provenance = $this->node('provenance', 'committal');
        $gate = $this->node('gate');
        $this->edge($provenance, $gate);
        // Synthetic and actual heads have the same local value; provenance is still counted.
        $this->rawValues([$provenance->id => 1]);
        $service = app(LineageMeasurementService::class);
        $counts = [Proposition::count(), Supersession::count()];
        $preview = $service->prospectiveCommit($gate, $this->phase);
        $this->assertSame($counts, [Proposition::count(), Supersession::count()]);
        $target = $this->node('target', 'committal');
        $this->edge($gate, $target, 'commit');
        $this->assertEquals($preview, $service->calculate($target));
        $this->assertSame(round((1 / 9) / (1 + 1 / 4 + 1 / 9), 7), $preview->depth);
    }

    public function test_obsolete_disposable_measurements_are_repaired_and_versioned(): void
    {
        $node = $this->node('origin', 'committal');
        $service = app(PropositionMeasurementService::class);
        $measurement = $service->project($node, 1, 1, 1);
        $measurement->calculator_version = null;
        $measurement->save();
        $node->unsetRelation('measurement');
        $service->repairMissing(collect([$node]));
        $this->assertSame(-1.0, $node->measurement->chirality);
        $this->assertSame(LineageMeasurementService::VERSION, $node->measurement->calculator_version);
    }

    public function test_entry_observer_attenuation_is_separate_from_measurement_accumulation_and_recovery(): void
    {
        $source = $this->node('source', 'committal');
        $continuation = $this->node('continuation');
        $observer = $this->node('observer');
        $this->edge($source, $continuation);
        $this->edge($observer, $source);
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill([
            'proposition_id' => $source->id,
            'substrate_id' => $this->phase->cycle->participant->substrate_id,
            'ledger_entry_id' => $source->ledgerEntry->id,
            'correspondence' => 0.8, 'chirality' => 0.8, 'closure' => 0.8,
            'prominence' => 0.9, 'turbulence' => 0.1, 'tension' => 0.8,
            'direction_before' => 'emergence', 'direction_after' => 'emergence',
            'calculator_version' => 'historical-fixture', 'evaluated_at' => now(),
        ])->save();
        $history = $snapshot->fresh()->getAttributes();
        $this->rawValues([$source->id => 0.8]);
        $values = app(PropositionMeasurementService::class)->recalculate($continuation);
        // (0 + .8/4) / (1 + 1/4) = .16; observer is beyond provenance.
        $this->assertSame(0.16, $values->chirality);
        $potential = app(PotentialChargeService::class);
        $entry = $potential->selectFor($this->phase->cycle->participant->substrate);
        $reentry = $potential->selectFrom($observer);
        $recovery = $potential->recoverFrom($observer);
        $this->assertSame(0.64, $entry['potential_charge']);
        $this->assertSame(0.64, $reentry['raw_potential_charge']);
        $this->assertSame(0.16, $reentry['potential_charge']); // .64 observed one edge away.
        $this->assertSame(0.05, $reentry['charge']);
        $this->assertSame(0.16, $values->fresh()->chirality); // No projection re-attenuation.
        $this->assertSame(0.64, $potential->selectFrom($source)['potential_charge']); // Distance zero.
        $this->assertSame(0.64, $recovery['potential_charge']);
        $this->assertSame(1, $recovery['distance']);
        $this->assertSame($history, $snapshot->fresh()->getAttributes());
    }

    private function rawValues(array $values): void
    {
        $this->mock(LocalMeasurementService::class, function ($mock) use ($values): void {
            $mock->shouldReceive('calculate')->andReturnUsing(function (Proposition $node) use ($values): LocalMeasurements {
                $value = (float) ($values[$node->id] ?? 0);
                return new LocalMeasurements($value, $value, $value, $value);
            });
        });
    }

    private function node(string $statement, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->phase->id, 'register' => 'canon', 'tier' => $tier, 'statement' => $statement])->save();
        app(LedgerService::class)->record($node);
        return $node;
    }

    private function edge(Proposition $from, Proposition $to, string $type = 'variant'): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->phase->id, 'source_proposition_id' => $from->id, 'target_proposition_id' => $to->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }
}
