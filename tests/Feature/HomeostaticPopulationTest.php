<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\HomeostaticDirectionService;
use App\Services\HomeostaticPopulationService;
use App\Services\HomologousPositionService;
use App\Services\LedgerService;
use App\Services\Observables\ObservableService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class HomeostaticPopulationTest extends TestCase
{
    use CreatesHomologousPositions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createHomologyActor();
    }

    public function test_population_includes_current_and_foreign_horizon_members_but_rejects_other_coordinates(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'settlement');
        $b = $this->commit($origin, 'settlement');
        $current = $this->position($a);
        $peer = $this->position($b);
        $local = $this->position($a);
        $this->position($b, PhaseKind::Bias);
        $this->position($b, register: 'stance');
        $this->position($this->commit($b, 'settlement'));
        $this->position($origin);
        $this->position(null);
        $this->position($this->commit($this->commit(null, 'emergence'), 'settlement'));
        $this->assertNotSame($current->phase->cycle->context_horizon_id, $peer->phase->cycle->context_horizon_id);
        $this->assertEqualsCanonicalizing([$current->id, $peer->id, $local->id],
            app(HomeostaticPopulationService::class)->forPosition($current)->pluck('id')->all());
    }

    public function test_position_domain_is_authoritative_even_when_entry_committals_were_created_in_different_domains(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($this->commit($origin, 'settlement'), 'settlement');
        $b = $this->commit($this->commit($origin, 'emergence'), 'settlement');
        $current = $this->position($a);
        $peer = $this->position($b);
        $homology = app(HomologousPositionService::class);
        $this->assertTrue($homology->arePositionsHomologous($current, $peer));
        $this->assertFalse($homology->areCommittedHomologous($a, $b));
        $this->assertEqualsCanonicalizing([$current->id, $peer->id],
            app(HomeostaticPopulationService::class)->forPosition($current)->pluck('id')->all());
        // Discovery now shares the proposition-Domain coordinate used by Mediate.
        $this->assertSame([$peer->id], $homology->candidatesFor($current, PhaseKind::Constrain)->pluck('id')->all());
    }

    public function test_committed_population_is_canonical_order_and_never_mixed_with_expressive_positions(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'emergence');
        $b = $this->commit($origin, 'settlement');
        $this->commit($b, 'settlement');
        $this->position($a, PhaseKind::Order);
        $this->assertEqualsCanonicalizing([$a->id, $b->id],
            app(HomeostaticPopulationService::class)->forPosition($a)->pluck('id')->all());
        $b->phase->kind = PhaseKind::Bias;
        $this->assertFalse(app(HomologousPositionService::class)->arePositionsHomologous($a, $b));
        $a->register = 'activity';
        $this->assertTrue(app(HomeostaticPopulationService::class)->forPosition($a)->isEmpty());
    }

    public function test_foreign_substrate_and_unsaved_anchor_cannot_enter_the_population(): void
    {
        $current = $this->position($this->commit(null, 'emergence'));
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'foreign', 'title' => 'Foreign', 'visibility' => 'private', 'configuration' => []])->save();
        $this->actor = new Participant;
        $this->actor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'foreign'])->save();
        $this->position($this->commit(null, 'emergence'));
        $service = app(HomeostaticPopulationService::class);
        $this->assertSame([$current->id], $service->forPosition($current)->pluck('id')->all());
        $this->assertTrue($service->forPosition(new Proposition)->isEmpty());
    }

    public function test_each_axis_aggregates_its_own_values_and_never_uses_the_acting_members_sign_as_authority(): void
    {
        $origin = $this->commit(null, 'emergence');
        $current = $this->causalPosition($this->commit($origin, 'settlement'));
        $b = $this->causalPosition($this->commit($origin, 'settlement'));
        $c = $this->causalPosition($this->commit($origin, 'settlement'));
        $projector = app(PropositionMeasurementService::class);
        $service = app(HomeostaticDirectionService::class);
        foreach ([1.0, -1.0] as $sign) {
            $this->controlledBirthClosureFixture(-.2 * $sign, $current->id);
            $this->controlledBirthClosureFixture(.6 * $sign, $b->id);
            $this->controlledBirthClosureFixture(.8 * $sign, $c->id);
            $projector->project($current, -.2 * $sign, .2 * $sign, -.2 * $sign);
            $projector->project($b, .6 * $sign, -.6 * $sign, .6 * $sign);
            $projector->project($c, .8 * $sign, -.8 * $sign, .8 * $sign);
            $before = DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson();
            foreach (['correspondence' => $sign, 'chirality' => -$sign, 'closure' => $sign] as $axis => $direction) {
                $result = $service->forPosition($current, $axis);
                $this->assertCount(3, $result->values);
                $this->assertSame(ObservableDirection::after($direction), $result->direction);
                $this->assertEqualsWithDelta(.4 * $direction, $result->mean, .0000001);
                $this->assertSame(-.2 * $direction, $result->values[$current->id]);
            }
            $this->assertSame($before, DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson());
        }
    }

    public function test_zero_population_directions_use_axis_defaults_or_history_without_any_writeback(): void
    {
        $position = $this->position($this->commit(null, 'emergence'));
        app(PropositionMeasurementService::class)->project($position, 0, 0, 0);
        $service = app(HomeostaticDirectionService::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            foreach (['correspondence' => ObservableDirection::Positive,
                'chirality' => ObservableDirection::Positive, 'closure' => ObservableDirection::Negative] as $axis => $default) {
                foreach ([null, ObservableDirection::Positive, ObservableDirection::Negative] as $previous) {
                    $result = $service->forPosition($position, $axis, $previous);
                    $this->assertSame(0.0, $result->mean);
                    $this->assertSame($previous ?? $default, $result->direction);
                    $this->assertSame([$position->id => 0.0], $result->values);
                }
            }
            foreach (DB::getQueryLog() as $query) {
                $this->assertDoesNotMatchRegularExpression('/\b(insert|update|delete|replace|merge)\b/i', $query['query']);
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertNull($position->fresh()->correspondence_direction);
        $this->assertSame(0.0, $position->fresh()->measurement->correspondence);
    }

    public function test_missing_and_obsolete_projections_are_derived_independently_without_writing(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->position($origin);
        $b = $this->position($origin);
        $independent = [];
        foreach ([$a, $b] as $node) {
            $independent[$node->id] = app(ObservableService::class)->calculate($node)->correspondence;
        }
        app(PropositionMeasurementService::class)->project($b, .75, .75, .75);
        PropositionMeasurement::query()->whereKey($a->id)->delete();
        DB::table('proposition_measurements')->where('proposition_id', $b->id)->update(['calculator_version' => 'obsolete']);
        $before = DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson();
        $result = app(HomeostaticDirectionService::class)->forPosition($a, 'correspondence');
        $this->assertEquals($independent, $result->values);
        $this->assertSame($before, DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson());
    }

    public function test_unledgered_members_remain_structural_but_do_not_supply_a_synthetic_measurement(): void
    {
        $origin = $this->commit(null, 'emergence');
        $current = $this->position($origin);
        $phase = $this->phase($origin, $origin, PhaseKind::Constrain);
        $missing = new Proposition;
        $missing->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive', 'register' => 'canon', 'statement' => 'Unledgered'])->save();
        app(PropositionMeasurementService::class)->project($current, .6, 0, 0);
        $result = app(HomeostaticDirectionService::class)->forPosition($current, 'correspondence');
        $this->assertArrayHasKey($missing->id, $result->values);
        $this->assertNull($result->values[$missing->id]);
        $this->assertSame(.6, $result->mean);
    }

    public function test_committed_members_use_frozen_snapshots_instead_of_disposable_projection_values(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->commit($origin, 'emergence', ['closure' => -.2]);
        $b = $this->commit($origin, 'emergence', ['closure' => .8]);
        $projector = app(PropositionMeasurementService::class);
        $projector->project($a, 0, 0, 0);
        $projector->project($b, 0, 0, 0);
        DB::table('proposition_measurements')->where('proposition_id', $b->id)->update(['closure' => -1]);
        $result = app(HomeostaticDirectionService::class)->forPosition($a, 'closure');
        $this->assertEquals([$a->id => -.2, $b->id => .8], $result->values);
        $this->assertSame(.3, $result->mean);
        $this->assertSame(ObservableDirection::Positive, $result->direction);
        $this->assertSame(-1.0, $b->fresh()->measurement->closure);
    }

    public function test_population_and_missing_measurement_hydration_are_bounded_across_foreign_horizons(): void
    {
        $origin = $this->commit(null, 'emergence');
        $current = $this->causalPosition($this->commit($origin, 'settlement'));
        $counts = [];
        foreach (range(1, 8) as $index) {
            $this->causalPosition($this->commit($origin, 'settlement'));
            if (! in_array($index, [1, 8], true)) {
                continue;
            }
            // Derive every member rather than relying on precomputed projections.
            PropositionMeasurement::query()->delete();
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $result = app(HomeostaticDirectionService::class)->forPosition($current->fresh(), 'correspondence');
                $queries = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount($index + 1, $result->values);
            $this->assertNotContains(null, $result->values);
            $counts[] = count($queries);
            $this->assertLessThan(100, count($queries));
        }
        $this->assertSame($counts[0], $counts[1], 'Queries must not grow per member or per sibling Horizon.');
    }

    public function test_reusing_complete_hydration_matches_private_measurement_without_expanding_an_incomplete_context(): void
    {
        $origin = $this->commit(null, 'emergence');
        $a = $this->causalPosition($this->commit($origin, 'settlement'));
        $b = $this->causalPosition($this->commit($origin, 'settlement'));
        $population = app(HomeostaticPopulationService::class)->forPosition($a);
        $service = app(HomeostaticDirectionService::class);
        $expected = $service->forPosition($a, 'correspondence');
        $incomplete = new ParticipationContext($a->phase);
        $before = $incomplete->propositions->keys()->all();
        $this->assertFalse($incomplete->propositions->has($b->id));
        $this->assertEquals($expected, $service->forPopulation($a, $population, 'correspondence', $incomplete));
        $this->assertSame($before, $incomplete->propositions->keys()->all());
        $complete = new ParticipationContext($a->phase, $population->pluck('id'));
        $this->assertEquals($expected, $service->forPopulation($a, $population, 'correspondence', $complete));
    }

    private function causalPosition(Proposition $entry): Proposition
    {
        $node = $this->position($entry);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $node->phase_id, 'source_proposition_id' => $entry->id,
            'target_proposition_id' => $node->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        app(\App\Services\Observables\ProspectiveClosureService::class)->initialize($node);

        return $node;
    }
}
