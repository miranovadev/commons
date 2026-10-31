<?php

namespace Tests\Feature;

use App\Domain\Observables\ChiralityValues;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\ChiralityPopulationHistoryService;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\HomeostaticDirectionService;
use App\Services\HomeostaticPopulationService;
use App\Services\HomologousPositionService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class ChiralityPopulationHistoryTest extends TestCase
{
    use CreatesHomologousPositions, RefreshDatabase;
    use IsolatesClosureOutput;

    public static function histories(): array
    {
        return ['positive history' => [.5, -.4, ObservableDirection::Positive],
            'negative history' => [.4, -.5, ObservableDirection::Negative]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    #[DataProvider('histories')]
    public function test_identical_zero_population_retains_its_own_actual_prior_direction(float $left, float $right, ObservableDirection $expected): void
    {
        $this->createHomologyActor();
        $origin = $this->committedCoordinate(null, 'emergence');
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
        $origin = $this->committedCoordinate(null, 'emergence');
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

    public function test_attestation_and_replacement_are_observed_through_real_dispatch(): void
    {
        $this->createHomologyActor();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/history-origin']]);
        $cycle = $this->actor->cycles()->latest('sequence')->firstOrFail();
        $phase = $cycle->phases()->whereNull('completed_at')->sole();
        $dispatcher = app(ContributionDispatcher::class);
        $this->instrumentGateFixture($phase);
        $node = $dispatcher->dispatch($this->actor, $phase, ['action' => 'proposition.variant', 'statement' => 'Observed source']);
        $history = app(ChiralityPopulationHistoryService::class);
        $key = $history->key($node);
        $assertObserved = function () use ($node, $key): void {
            // One expressive member with its actual Origin at distance one.
            // Population history must capture resolved vNext Chirality, not the retired rate scalar.
            $local = app(LocalMeasurementService::class);
            $origin = $node->parents()->sole();
            $expected = round(($local->chiralityComponents($node)->average()
                + $local->chiralityComponents($origin)->average() / 4) / 1.25, 7);
            $this->assertSame($expected, (float) DB::table('chirality_population_observations')->where('population_key', $key)->latest('id')->value('mean'));
            $this->assertSame($expected, app(HomeostaticDirectionService::class)->forPosition($node->fresh(), 'chirality')->mean);
        };
        $this->assertSame(0.0, app(LocalMeasurementService::class)->chiralityComponents($node)->orientation);
        $assertObserved();
        $beforeReceipt = DB::table('chirality_population_observations')->get()->toJson();
        // Component fixture: historical shared observation, independent of a
        // current generative attempt; personal Attestation remains real dispatch.
        $this->observationFixture($node->phase, $node,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/history-evidence']);
        $this->assertSame($beforeReceipt, DB::table('chirality_population_observations')->get()->toJson());
        $dispatcher->dispatch($this->actor, $phase, ['action' => 'attestation.create', 'proposition_id' => $node->id, 'direction' => 'support']);
        $this->assertSame(.5, app(LocalMeasurementService::class)->chiralityComponents($node)->orientation);
        $assertObserved();
        $phase->forceFill(['completed_at' => now()])->save();
        $next = new Phase;
        $next->forceFill(['cycle_id' => $cycle->id, 'kind' => $phase->kind, 'write_register' => $phase->write_register,
            'gate_proposition_id' => $phase->gate_proposition_id, 'started_at' => now()])->save();
        $dispatcher->dispatch($this->actor, $next, ['action' => 'attestation.create', 'proposition_id' => $node->id, 'direction' => 'refute']);
        $this->assertSame(2, app(LocalMeasurementService::class)->chiralityComponents($node)->currentActs);
        $this->assertSame(1, Attestation::whereNotNull('supersedes_attestation_id')->count());
        $this->assertSame(-.5, app(LocalMeasurementService::class)->chiralityComponents($node)->orientation);
        $this->assertSame(1, Attestation::effective()->where('proposition_id', $node->id)->count());
        $assertObserved();
    }

    public function test_batched_observation_coordinates_match_the_full_canonical_populations(): void
    {
        $this->createHomologyActor();
        $origin = $this->committedCoordinate(null, 'emergence');
        $this->position($origin);
        $a = $this->committedCoordinate($origin, 'settlement');
        $b = $this->committedCoordinate($origin, 'settlement');
        $this->position($a);
        $this->position($b);
        $this->position($a, PhaseKind::Mediate);
        $this->position($this->committedCoordinate($a, 'settlement'));
        $positions = Proposition::all();
        $homology = app(HomologousPositionService::class);
        $keys = $homology->populationKeys($positions);
        foreach ($positions as $position) {
            $this->assertSame($homology->populationKey($position), $keys[$position->id] ?? null);
            $actual = app(HomeostaticPopulationService::class)->forPosition($position)->pluck('id')->all();
            $group = array_keys(array_filter($keys, fn ($key) => $key === ($keys[$position->id] ?? null)));
            $this->assertEqualsCanonicalizing($actual, $group);
        }
    }

    public function test_unavailable_current_population_inherits_corresponding_preceding_horizon_without_a_synthetic_zero(): void
    {
        $this->createHomologyActor();
        $origin = $this->committedCoordinate(null, 'emergence');
        $prior = $this->position($origin);
        $this->partialMock(LocalMeasurementService::class, function ($mock) {
            $mock->shouldReceive('chiralityComponents')->andReturn(new ChiralityValues(-.5, -.5));
        });
        app(ChiralityPopulationHistoryService::class)->observe($this->actor->substrate_id);
        $boundary = $this->committedCoordinate($origin, 'settlement');
        $current = $this->position($boundary);
        $before = DB::table('chirality_population_observations')->count();
        $result = app(HomeostaticDirectionService::class)->forPopulation($current, collect(), 'chirality');
        $this->assertNull($result->mean);
        $this->assertSame([], $result->values);
        $this->assertSame(ObservableDirection::Negative, $result->direction);
        $this->assertSame($before, DB::table('chirality_population_observations')->count());
    }
}
