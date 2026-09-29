<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\CrossoverMarginService;
use App\Services\CycleEntryService;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrossoverRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->substrate = (new Substrate)->forceFill(['slug' => 'crossover', 'title' => 'Crossover', 'visibility' => 'public', 'configuration' => []]);
        $this->substrate->save();
    }

    private function phase(?Proposition $seed, PhaseKind $kind, ?Proposition $gate = null): Phase
    {
        $gate ??= $seed;
        $actor = (new Participant)->forceFill(['substrate_id' => $this->substrate->id,
            'callsign' => (string) str()->ulid(), 'current_proposition_id' => $gate?->id]);
        $actor->save();
        $horizon = $seed === null ? app(ContextHorizonProjector::class)->currentFor($this->substrate)
            : app(ContextHorizonProjector::class)->forEntry($seed);
        $cycle = (new Cycle)->forceFill(['participant_id' => $actor->id, 'sequence' => 1,
            'context_horizon_id' => $horizon->id, 'entry_committal_id' => $seed?->id]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'gate_proposition_id' => $gate?->id,
            'kind' => $kind, 'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()]);
        $phase->save();

        return $phase;
    }

    private function node(Phase $phase, bool $committal = false): Proposition
    {
        $node = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => $committal ? 'committal' : 'expressive', 'statement' => 'Controlled committed geometry']);
        $node->save();
        app(LedgerService::class)->record($node);
        if ($phase->gate_proposition_id !== null) {
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $phase->gate_proposition_id,
                'target_proposition_id' => $node->id, 'type' => $committal ? 'commit' : 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
        }
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now()])->save();

        return $node;
    }

    private function commit(?Proposition $parent, float $value = 0.2, bool $boundary = false, ?int $interval = 1): Proposition
    {
        $node = $this->node($this->phase($parent, PhaseKind::Order), true);
        $domain = $node->phase->cycle->contextHorizon->domain;
        $snapshot = (new CommitmentSnapshot)->forceFill(['proposition_id' => $node->id,
            'substrate_id' => $this->substrate->id, 'ledger_entry_id' => $node->ledgerEntry->id,
            'correspondence' => $value, 'chirality' => $value, 'closure' => -1,
            // Deliberately unlike the next Commit's Depth: never reuse this value.
            'depth' => 0.9, 'cadence' => 0.9, 'depth_direction' => 'positive', 'cadence_direction' => 'positive',
            'cadence_interval' => $parent === null ? null : $interval,
            'prominence' => 0.4, 'turbulence' => 0.6, 'tension' => -0.2,
            'direction_before' => $domain, 'direction_after' => $boundary ? $domain->opposite() : $domain,
            'calculator_version' => $parent === null ? 'origin-bootstrap-v1' : 'controlled-crossover-fixture', 'evaluated_at' => now()]);
        $snapshot->save();
        app(ContextHorizonProjector::class)->apply($snapshot);

        return $node;
    }

    private function terminal(Proposition $seed): Phase
    {
        // A Constrain-produced Gate cannot Commit at Order. The ordinary plan,
        // not a mocked Rest flag, must establish that no forward action exists.
        $gate = $this->node($this->phase($seed, PhaseKind::Constrain));
        $phase = $this->phase($seed, PhaseKind::Order, $gate);
        app(PotentialProjectionService::class)->refresh($this->substrate->id);

        return $phase;
    }

    public static function margins(): array
    {
        return ['open' => [0.2, false], 'closed' => [-0.2, true]];
    }

    #[DataProvider('margins')]
    public function test_genuine_rest_routes_only_the_resting_participant(float $value, bool $closed): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, $value);
        $sibling = $this->commit($parent, 0.99);
        $terminal = $this->terminal($seed);
        $other = $this->phase($seed, PhaseKind::Distinguish);
        app(PotentialProjectionService::class)->refresh($this->substrate->id);
        $diagnostic = app(CrossoverMarginService::class)->inspect($terminal->cycle);
        $this->assertSame(-0.3333333, $diagnostic['prospective_depth']);
        $this->assertSame(0.0, $diagnostic['admissible_cadence_bound']);
        $this->assertSame($closed, $diagnostic['closed']);
        $this->assertArrayNotHasKey('reinforcement_mass', $diagnostic);
        $actor = $terminal->cycle->participant;
        $this->assertTrue(app(InputPlanService::class)->forPhase($actor, $terminal)['rest_available']);
        $before = DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->toJson();
        app(ParticipationService::class)->contribute($actor, $terminal->id, ['action' => 'participant.rest']);
        $this->assertSame($closed ? $parent->id : $seed->id, $actor->fresh()->current_proposition_id);
        $this->assertNotSame($sibling->id, $actor->fresh()->current_proposition_id);
        $this->assertNotNull($terminal->cycle->fresh()->rested_at);
        $this->assertSame($before, DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->toJson());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($seed));
        $this->assertSame($seed->id, $other->cycle->participant->fresh()->current_proposition_id);
        $this->assertFalse(app(InputPlanService::class)->forPhase($other->cycle->participant, $other)['rest_available']);
        app(ParticipationService::class)->contribute($other->cycle->participant, $other->id,
            ['action' => 'proposition.variant', 'statement' => 'Another participant continues this open Region.']);
        $this->assertSame($seed->id, $other->cycle->fresh()->entry_committal_id);
        $this->assertNotSame($seed->id, $other->cycle->participant->fresh()->current_proposition_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_closed_crossover_does_not_enable_rest_or_proactive_recovery(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, -0.2);
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $actor = $phase->cycle->participant;
        app(PotentialProjectionService::class)->refresh($this->substrate->id);
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($phase->cycle)['closed']);
        $this->assertFalse(app(InputPlanService::class)->forPhase($actor, $phase)['rest_available']);
        app(CycleEntryService::class)->prepare($actor);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($seed, $phase->cycle->contextHorizon, $actor);
        $this->assertNull($inspection['rest_skipped_committal_id']);
        $this->assertSame($seed->id, $inspection['selection']['proposition']->id);
        $this->assertSame($seed->id, $actor->fresh()->current_proposition_id);
        try {
            app(ParticipationService::class)->contribute($actor, $phase->id, ['action' => 'participant.rest']);
            $this->fail('Rest must remain unavailable.');
        } catch (\InvalidArgumentException) {
            $this->assertNull($phase->cycle->fresh()->rested_at);
        }
    }

    public function test_closed_boundary_region_uses_recorded_parent_horizon_and_keeps_origin_fallback(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $boundary = $this->commit($parent, 0.8, boundary: true);
        $terminal = $this->terminal($boundary);
        $this->assertSame('emergence', $terminal->cycle->contextHorizon->domain->value);
        $diagnostic = app(CrossoverMarginService::class)->inspect($terminal->cycle);
        $this->assertSame(0.0, $diagnostic['prospective_depth']);
        $this->assertSame(0.0, $diagnostic['admissible_cadence_bound']);
        $this->assertTrue($diagnostic['closed']);
        $actor = $terminal->cycle->participant;
        app(ParticipationService::class)->contribute($actor, $terminal->id, ['action' => 'participant.rest']);
        $this->assertSame($parent->id, $actor->fresh()->current_proposition_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($terminal->cycle->contextHorizon));

        $origin->commitmentSnapshot->forceFill(['correspondence' => -1, 'chirality' => -1])->save();
        $atOrigin = $this->terminal($origin->fresh());
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($atOrigin->cycle)['closed']);
        app(ParticipationService::class)->contribute($atOrigin->cycle->participant, $atOrigin->id, ['action' => 'participant.rest']);
        $this->assertSame($origin->id, $atOrigin->cycle->participant->fresh()->current_proposition_id);
    }

    public function test_zero_margin_retains_domain_and_zero_depth_retains_its_previous_direction(): void
    {
        $origin = $this->commit(null, boundary: true);
        $seed = $this->commit($origin, 0);
        $seed->commitmentSnapshot->forceFill(['depth_direction' => 'negative'])->save();
        $phase = $this->phase($seed, PhaseKind::Order);
        $diagnostic = app(CrossoverMarginService::class)->inspect($phase->cycle);
        $this->assertSame(0.0, $diagnostic['prospective_depth']);
        $this->assertSame(0.0, $diagnostic['crossover_margin']);
        $this->assertTrue($diagnostic['closed']);
        $seed->commitmentSnapshot->forceFill(['depth_direction' => 'positive'])->save();
        $positive = app(CrossoverMarginService::class)->inspect($phase->cycle);
        $this->assertSame(1.0, $positive['admissible_cadence_bound']);
        $this->assertFalse($positive['closed']);
    }

    public function test_reinforcement_changes_no_crossover_bound_or_measurement(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, 0);
        $phase = $this->phase($seed, PhaseKind::Order);
        $before = app(CrossoverMarginService::class)->inspect($phase->cycle);
        $this->loadedReceiptFixture($seed);
        $state = fn () => DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson();
        $measurements = $state();
        $after = app(CrossoverMarginService::class)->inspect($phase->cycle);
        $this->assertSame(1, app(ReinforcementService::class)->count($seed));
        $this->assertSame($before, $after);
        $this->assertSame($measurements, $state());
        $this->loadedReceiptFixture($seed);
        $measurements = $state();
        $this->assertSame(2, app(ReinforcementService::class)->count($seed));
        $this->assertSame($before, app(CrossoverMarginService::class)->inspect($phase->cycle));
        $this->assertSame($measurements, $state());
    }

    public function test_missing_required_history_fails_closed_as_unsupported_state(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, -1, interval: null);
        $this->expectException(ClosureHistoryUnavailable::class);
        $this->terminal($seed);
    }

    public function test_authoritative_exhaustion_still_skips_an_open_crossover_region(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, 0.2);
        $terminal = $this->terminal($seed);
        $this->assertFalse(app(CrossoverMarginService::class)->inspect($terminal->cycle)['closed']);
        $this->boundaryTragedyFixture($this->node($this->phase($seed, PhaseKind::Distinguish)));
        $this->boundaryTragedyFixture($this->node($this->phase($seed, PhaseKind::Distinguish)));
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        app(ParticipationService::class)->contribute($terminal->cycle->participant, $terminal->id, ['action' => 'participant.rest']);
        $this->assertSame($parent->id, $terminal->cycle->participant->fresh()->current_proposition_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }
}
