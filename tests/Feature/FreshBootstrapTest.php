<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Supersession;
use App\Services\ChiralityGateService;
use App\Services\ClosureGateService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\TestCase;

#[Group('integration')]
class FreshBootstrapTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public static function polarityLabels(): array
    {
        return [['support'], ['refute']];
    }

    #[DataProvider('polarityLabels')]
    public function test_explicit_origin_and_aligned_evidence_reach_emergence(string $label): void
    {
        $phases = [];
        $biasAvailable = false;
        $commitPreview = null;
        $commit = $this->driveHorizonCycle(realClosure: true, originDirection: $label,
            observe: function ($participant, $state) use (&$phases, &$biasAvailable, &$commitPreview): void {
                $plan = $state['plan'];
                $phases[$plan['phase']['number']] = true;
                if ($commitPreview === null && isset($plan['schema']['$defs']['actions']['proposition.commit'])) {
                    $phase = Phase::findOrFail($plan['phase']['id']);
                    $gate = $phase->gate;
                    $closure = app(ClosureGateService::class);
                    $commitPreview = $closure->preview($gate, $phase);
                    $this->assertSame(0.0, $commitPreview['depth']);
                    $this->assertSame(0.0, $commitPreview['cadence']);
                    $this->assertTrue($commitPreview['admissible']);
                    $this->assertSame(0.0, app(LocalMeasurementService::class)->calculate($gate)->closure()->average());
                }
                if ($plan['phase']['number'] === 6 && isset($plan['schema']['$defs']['actions']['proposition.reclassify'])) {
                    $biasAvailable = true;
                    $gate = Proposition::findOrFail($plan['gate_id']);
                    $this->assertTrue(app(ChiralityGateService::class)->evaluate($gate)['admissible']);
                }
            });
        $this->assertEqualsCanonicalizing(range(1, 7), array_keys($phases));
        $this->assertTrue($biasAvailable);
        $this->assertSame(2, CommitmentSnapshot::count());
        $origin = CommitmentSnapshot::where('calculator_version', 'origin-bootstrap-v1')->sole();
        $this->assertSame(1, Attestation::where('proposition_id', $origin->proposition_id)->count());
        $this->assertSame(0.0, $origin->orientation);
        $this->assertSame(-1.0, $origin->attestation_cadence);
        $this->assertSame(-0.5, $origin->chirality);
        $this->assertSame('negative', $origin->orientation_direction);
        $this->assertSame('negative', $origin->attestation_cadence_direction);
        $this->assertSame($label, $origin->proposition->attestations()->sole()->direction);
        $this->assertSame(0, Receipt::where('proposition_id', $origin->proposition_id)->count());
        $snapshot = $commit->commitmentSnapshot;
        $this->assertSame(0.0, $snapshot->depth);
        $this->assertSame(0.0, $snapshot->cadence);
        $this->assertSame(-0.5276331, $snapshot->correspondence);
        $this->assertSame(0.8454858, $snapshot->chirality);
        $this->assertSame(1.0, $snapshot->orientation);
        $this->assertSame(1.0, $snapshot->attestation_cadence);
        $this->assertSame(ContextDomain::Emergence, $snapshot->direction_after);
        $this->assertSame(0.1059509, $snapshot->tension);
        $this->assertSame(ContextDomain::after($snapshot->tension, $snapshot->direction_before), $snapshot->direction_after);
        $this->assertSame($snapshot->direction_after, $commit->phase->cycle->participant->cycles()->latest('sequence')->first()->contextHorizon->domain);
        $this->assertSame($commitPreview['cadence_interval'], $snapshot->cadence_interval);
        $this->assertSame($commitPreview['closure'], $snapshot->closure);
        $this->assertSame(Cycle::where('entry_committal_id', $origin->proposition_id)
            ->where('context_horizon_id', $commit->phase->cycle->context_horizon_id)->count(), $snapshot->cadence_interval);
        $this->assertTrue(Supersession::where('type', 'reclassify')
            ->whereHas('phase', fn ($q) => $q->where('kind', 6))->exists());
        $witnesses = [];
        foreach ([1, 3, 7] as $phaseNumber) {
            $node = Proposition::whereHas('phase', fn ($q) => $q->where('kind', $phaseNumber))
                ->where('id', '!=', $origin->proposition_id)->orderBy('id')->firstOrFail();
            $lineage = app(LineageMeasurementService::class);
            $local = app(LocalMeasurementService::class)->calculate($node);
            $measured = $lineage->calculate($node);
            $this->assertSame($measured->chirality, $lineage->chirality($node)[0]);
            $this->assertLessThan(.261881, $measured->correspondence);
            $witnesses[$phaseNumber] = [$local->correspondenceComponents->shapeTopology,
                $local->correspondenceComponents->cardinalPosition, $measured->correspondence];
        }
        $this->assertSame([-.5285955, 0.0, -.0114382], $witnesses[1]);
        $this->assertSame([-.3827866, -.5, -.3700546], $witnesses[3]);
        $this->assertSame([-.3420483, -.75, -.5276331], $witnesses[7]);
        $this->assertSame(1, $commit->commitSources()->count());
    }
}
