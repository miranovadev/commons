<?php

namespace Tests\Feature;

use App\Domain\Observables\ChiralityValues;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Services\ChiralityPopulationHistoryService;
use App\Services\HomeostaticDirectionService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class ChiralityPopulationHistoryTest extends TestCase
{
    use CreatesHomologousPositions, RefreshDatabase;

    public static function histories(): array
    {
        return ['positive history' => [.5, -.4, ObservableDirection::Positive],
            'negative history' => [.4, -.5, ObservableDirection::Negative]];
    }

    #[DataProvider('histories')]
    public function test_identical_zero_population_retains_its_own_actual_prior_direction(float $left, float $right, ObservableDirection $expected): void
    {
        $this->createHomologyActor();
        $origin = $this->commit(null, 'emergence');
        $a = $this->position($origin);
        $b = $this->position($origin);
        $values = [$a->id => $left, $b->id => $right];
        $this->partialMock(LocalMeasurementService::class, function ($mock) use (&$values) {
            $mock->shouldReceive('chiralityComponents')->andReturnUsing(function ($node) use (&$values) {
                return new ChiralityValues($values[$node->id], $values[$node->id]);
            });
        });
        $projector = app(PropositionMeasurementService::class);
        foreach ([$a, $b] as $node) {
            $projector->project($node, 0, $values[$node->id], 0);
        }
        $history = app(ChiralityPopulationHistoryService::class);
        $history->observe($this->actor->substrate_id);
        $values = [$a->id => .5, $b->id => -.5];
        $projections = DB::table('proposition_measurements')->get()->toJson();
        $history->observe($this->actor->substrate_id);
        $this->assertSame($projections, DB::table('proposition_measurements')->get()->toJson());
        foreach ([$a, $b] as $node) {
            $projector->project($node, 0, $values[$node->id], 0);
        }
        $before = DB::table('chirality_population_observations')->get()->toJson();
        foreach ([$a, $b] as $anchor) {
            $result = app(HomeostaticDirectionService::class)->forPosition($anchor, 'chirality');
            $this->assertSame(0.0, $result->mean);
            $this->assertSame($expected, $result->direction);
        }
        $history->observe($this->actor->substrate_id); // Repeated zero is not a fresh initialization.
        $this->assertSame($before, DB::table('chirality_population_observations')->get()->toJson());
        $this->assertSame('positive', $a->fresh()->measurement->chirality_direction);
        $this->assertSame('negative', $b->fresh()->measurement->chirality_direction);
    }

    public function test_cycle_entry_observes_both_sides_even_without_attestation_or_completion_and_rolls_back_atomically(): void
    {
        $this->createHomologyActor();
        $origin = $this->commit(null, 'emergence');
        $a = $this->position($origin);
        $b = $this->position($origin);
        $count = Cycle::where('entry_committal_id', $origin->id)->count();
        $this->partialMock(LocalMeasurementService::class, function ($mock) use ($origin, $count, $a) {
            $mock->shouldReceive('chiralityComponents')->andReturnUsing(function ($node) use ($origin, $count, $a) {
                $value = $node->id === $a->id ? .5 : (Cycle::where('entry_committal_id', $origin->id)->count() === $count ? -.4 : -.5);

                return new ChiralityValues($value, $value);
            });
        });
        foreach ([$a, $b] as $node) {
            app(PropositionMeasurementService::class)->project($node, 0, 0, 0);
        }
        $before = DB::table('chirality_population_observations')->get()->toJson();
        $measurements = DB::table('proposition_measurements')->get()->toJson();
        try {
            DB::transaction(function () use ($origin) {
                $this->phase($origin, $origin, PhaseKind::Distinguish);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('rollback', $error->getMessage());
        }
        $this->assertSame($before, DB::table('chirality_population_observations')->get()->toJson());
        $this->assertSame($measurements, DB::table('proposition_measurements')->get()->toJson());
        $new = $this->phase($origin, $origin, PhaseKind::Distinguish);
        $this->assertNull($new->cycle->completed_at);
        $this->assertDatabaseCount('attestations', 0);
        $result = app(HomeostaticDirectionService::class)->forPosition($a, 'chirality');
        $this->assertSame(0.0, $result->mean);
        $this->assertSame(ObservableDirection::Positive, $result->direction);
        $means = DB::table('chirality_population_observations')->where('population_key', app(ChiralityPopulationHistoryService::class)->key($a))->orderBy('id')->pluck('mean');
        $this->assertSame([.05, 0.0], $means->take(-2)->map(fn ($v) => (float) $v)->values()->all());
    }

    public function test_unavailable_current_population_inherits_corresponding_preceding_horizon_without_a_synthetic_zero(): void
    {
        $this->createHomologyActor();
        $origin = $this->commit(null, 'emergence');
        $prior = $this->position($origin);
        $this->partialMock(LocalMeasurementService::class, function ($mock) {
            $mock->shouldReceive('chiralityComponents')->andReturn(new ChiralityValues(-.5, -.5));
        });
        app(ChiralityPopulationHistoryService::class)->observe($this->actor->substrate_id);
        $boundary = $this->commit($origin, 'settlement');
        $current = $this->position($boundary);
        $before = DB::table('chirality_population_observations')->count();
        $result = app(HomeostaticDirectionService::class)->forPopulation($current, collect(), 'chirality');
        $this->assertNull($result->mean);
        $this->assertSame([], $result->values);
        $this->assertSame(ObservableDirection::Negative, $result->direction);
        $this->assertSame($before, DB::table('chirality_population_observations')->count());
    }
}
