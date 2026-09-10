<?php

namespace Tests\Feature;

use App\Domain\Observables\LocalMeasurements;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\LedgerService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\PropositionMeasurementService;
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
