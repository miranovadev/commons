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
use App\Services\AcquisitionAttemptService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\CrossoverMarginService;
use App\Services\CycleEntryService;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\ClosureBirthService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class CrossoverRecoveryTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    private Substrate $substrate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
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
        return ['open' => [0.2, false], 'closed' => [-0.8, true]];
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
        $this->assertSame($seed->id, $actor->fresh()->current_proposition_id);
        $this->assertNotSame($sibling->id, $actor->fresh()->current_proposition_id);
        $this->assertNotNull($terminal->cycle->fresh()->rested_at);
        $this->assertSame($before, DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->toJson());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($seed));
        $this->assertSame($seed->id, $other->cycle->participant->fresh()->current_proposition_id);
        $plan = app(InputPlanService::class)->forPhase($other->cycle->participant, $other);
        $this->assertSame($closed, $plan['rest_available']);
        $this->assertSame(! $closed, isset($plan['schema']['$defs']['actions']['instrument.execute']));
        $this->assertSame($seed->id, $other->cycle->fresh()->entry_committal_id);
    }

    public function test_rest_at_the_entry_itself_moves_one_committal_back(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, -0.8);
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $actor = $phase->cycle->participant;
        app(PotentialProjectionService::class)->refresh($this->substrate->id);
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($phase->cycle)['closed']);
        app(CycleEntryService::class)->prepare($actor);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($seed, $phase->cycle->contextHorizon, $actor);
        $this->assertSame($seed->id, $inspection['selection']['proposition']->id);
        $this->assertSame($seed->id, $actor->fresh()->current_proposition_id);
        $state = app(ParticipationService::class)->contribute($actor, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame($parent->id, $state['current_proposition_id']);
        $this->assertSame($seed->id, $phase->cycle->fresh()->entry_committal_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_a_new_opportunity_after_rest_cannot_displace_an_authorised_backward_destination(): void
    {
        $parent = $this->commit(null, boundary: true);
        $seed = $this->commit($parent, -0.8);
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $actor = $phase->cycle->participant;
        $this->publishPotentialFixture($this->substrate);
        $this->assertSame(['participant.rest'], array_keys(app(InputPlanService::class)->forPhase($actor, $phase)['schema']['$defs']['actions']));

        // Isolate the interval between completed Rest and the next entry attempt.
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $write = $this->node($this->phase($seed, PhaseKind::Distinguish));
        $this->receiptFixture($write);
        $this->publishPotentialFixture($this->substrate);
        $state = app(ParticipationService::class)->prepare($actor);
        $this->assertSame($parent->id, $state['current_proposition_id']);
        $this->assertSame($seed->id, $phase->cycle->fresh()->entry_committal_id);
    }

    public function test_closed_boundary_and_origin_retain_their_horizon_and_wait_after_rest_at_entry(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $boundary = $this->commit($parent, 0.8, boundary: true);
        $terminal = $this->terminal($boundary);
        $this->assertSame('emergence', $terminal->cycle->contextHorizon->domain->value);
        $diagnostic = app(CrossoverMarginService::class)->inspect($terminal->cycle);
        $this->assertSame(1.0, $diagnostic['prospective_depth']);
        $this->assertSame(.5, $diagnostic['prospective_committed_cardinal_position']);
        $this->assertSame(0.0, $diagnostic['admissible_cadence_bound']);
        $this->assertTrue($diagnostic['closed']);
        $origin->commitmentSnapshot->forceFill(['correspondence' => -1, 'chirality' => -1])->save();
        $atOrigin = $this->terminal($origin->fresh());
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($atOrigin->cycle)['closed']);

        foreach ([$terminal, $atOrigin] as $phase) {
            $actor = $phase->cycle->participant;
            $service = app(ParticipationService::class);
            $entry = $service->contribute($actor, $phase->id, ['action' => 'participant.rest']);
            $this->assertSame($phase->cycle->entry_committal_id, $entry['current_proposition_id']);
            $this->assertSame(['participant.rest'], array_keys($entry['plan']['schema']['$defs']['actions']));
            $count = $actor->cycles()->count();
            $waiting = $service->contribute($actor, $entry['plan']['phase']['id'], ['action' => 'participant.rest']);
            $this->assertSame('recovery_unavailable', $waiting['reason']['code']);
            $this->assertSame('waiting', $service->prepare($actor)['status']);
            $this->assertSame($count, $actor->cycles()->count());
            $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($phase->cycle->contextHorizon));
        }
        // Later actionable structure can wake the waiting participant. The
        // old Rest and its Phase remain completed and repeated polls create one entry.
        $write = $this->node($this->phase($boundary, PhaseKind::Distinguish));
        $this->receiptFixture($write);
        $this->publishPotentialFixture($this->substrate);
        $actor = $terminal->cycle->participant;
        $state = app(ParticipationService::class)->prepare($actor);
        $this->assertSame($boundary->id, $state['current_proposition_id']);
        $this->assertSame([$write->id], $state['plan']['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum']);
        $this->assertSame(2, $actor->cycles()->whereNotNull('rested_at')->count());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_two_participants_finish_historical_siblings_independently_in_a_closed_region(): void
    {
        $parent = $this->commit(null, boundary: true);
        $seed = $this->commit($parent, -0.8);
        // These two expressive routes predate the new Variant rule. From entry
        // onward every judgment, Traverse, Rest and recovery uses participation.
        $route = [];
        $gate = $seed;
        foreach ([PhaseKind::Distinguish, PhaseKind::Persist] as $kind) {
            $gate = $this->node($this->phase($seed, $kind, $gate));
            $route[] = $gate;
        }
        $this->classificationEvidenceFixture($gate);
        $classify = $this->phase($seed, PhaseKind::Constrain, $gate);
        $gate = app(ContributionDispatcher::class)->dispatch($classify->cycle->participant, $classify,
            $this->reclassificationInputFixture($classify));
        $route[] = $gate;
        $left = $this->node($this->phase($seed, PhaseKind::Disperse, $gate));
        $right = $this->node($this->phase($seed, PhaseKind::Disperse, $gate));
        $this->receiptFixture($left);
        $luna = $this->phase($seed, PhaseKind::Distinguish)->cycle->participant;
        $vela = $this->phase($seed, PhaseKind::Distinguish)->cycle->participant;
        $nova = $this->phase($seed, PhaseKind::Distinguish)->cycle->participant;
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($luna->cycles()->sole())['closed']);
        $this->publishPotentialFixture($this->substrate);
        $this->assertTrue(app(PotentialChargeService::class)->hasRemainingInRegion($luna->cycles()->sole()->currentPhase));
        $service = app(ParticipationService::class);
        $act = function (Participant $participant, string $action, ?Proposition $target = null) use ($service): array {
            $state = $service->stateFor($participant->fresh());
            $actions = $state['plan']['schema']['$defs']['actions'];
            foreach (['instrument.execute', 'preparation.start', 'proposition.variant'] as $fresh) {
                $this->assertArrayNotHasKey($fresh, $actions);
            }
            $input = ['action' => $action];
            if ($target !== null) {
                $input['proposition_id'] = $target->id;
                $this->assertContains($target->id, $actions[$action]['properties']['proposition_id']['enum']);
            }
            if ($action === 'attestation.create') {
                $input['direction'] = 'support';
            }

            return $service->contribute($participant, $state['plan']['phase']['id'], $input);
        };

        foreach ([$luna, $vela] as $actor) {
            foreach ([...$route, $left] as $target) {
                $act($actor, 'attestation.create', $target);
            }
            $returned = $act($actor, 'participant.rest');
            $this->assertSame($seed->id, $returned['current_proposition_id']);
            $this->assertFalse($returned['plan']['rest_available']);
        }
        foreach ($route as $target) {
            $act($vela, 'proposition.traverse', $target);
        }
        $act($vela, 'attestation.create', $right);
        $finished = $act($vela, 'participant.rest');
        $this->assertSame($seed->id, $finished['current_proposition_id']);
        $this->assertSame(['participant.rest'], array_keys($finished['plan']['schema']['$defs']['actions']));
        $this->assertSame($parent->id, $act($vela, 'participant.rest')['current_proposition_id']);
        $this->assertDatabaseCount('exploration_events', 3);
        foreach ($route as $target) {
            $unfinished = $act($luna, 'proposition.traverse', $target);
        }
        $this->assertSame([$right->id], $unfinished['plan']['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum']);
        $act($luna, 'attestation.create', $right);
        $act($nova, 'attestation.create', $route[0]);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public static function variantPhases(): array
    {
        return [[PhaseKind::Distinguish], [PhaseKind::Persist], [PhaseKind::Disperse]];
    }

    #[DataProvider('variantPhases')]
    public function test_margin_governs_variant_admission_and_resolution_in_each_phase(PhaseKind $kind): void
    {
        $origin = $this->commit(null, boundary: true);
        $seed = $this->commit($this->commit($origin));
        $gate = $kind === PhaseKind::Distinguish ? $seed
            : $this->node($this->phase($seed, PhaseKind::from($kind->value - 1)));
        $phase = $this->phase($seed, $kind, $gate);
        $attempts = app(AcquisitionAttemptService::class);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse(app(CrossoverMarginService::class)->inspect($phase->cycle)['closed']);
        $this->assertSame('required', $attempts->state($phase)['status']);
        $this->assertTrue($attempts->allows($phase, 'instrument.execute'));
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $this->instrumentGateFixture($phase);
        $this->assertTrue($generation->available($phase, 'proposition.variant'));
        $execution = $attempts->execution($phase);

        // Change only the fixture's committed inputs to isolate revalidation.
        $seed->commitmentSnapshot->forceFill(['correspondence' => -0.8, 'chirality' => -0.8])->save();
        $other = $this->phase($seed, $kind, $gate);
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($phase->cycle)['closed']);
        $this->assertSame('not_required', $attempts->state($other)['status']);
        $this->assertFalse($attempts->allows($other, 'instrument.execute'));
        $this->assertFalse($attempts->allows($other, 'preparation.start'));
        try {
            app(OperationalInstrumentService::class)->execute($other, $gate, ['kind' => 'citation', 'reference' => $seed->id]);
            $this->fail('A closed Region must reject admission before reserving an Instrument.');
        } catch (ConflictHttpException) {
            $this->assertNull($attempts->execution($other));
        }
        $this->assertFalse($generation->available($phase, 'proposition.variant', prepared: true));
        $this->assertSame(['acquisition.defer'], array_keys($attempts->schema($phase, $execution)['$defs']['actions']));
        $count = Proposition::count();
        try {
            $attempts->resolve($phase->cycle->participant, $execution, ['action' => 'proposition.variant', 'statement' => 'A stale resolution']);
            $this->fail('Previously admitted Instrument cannot generate through a closed margin.');
        } catch (\InvalidArgumentException) {
            $this->assertNull($execution->fresh()->attempt_resolution);
        }
        $attempts->resolve($phase->cycle->participant, $execution, ['action' => 'acquisition.defer',
            'execution_id' => $execution->id, 'reason' => 'The committed geometry now precludes another Variant.']);
        $this->assertSame($count, Proposition::count());
        $this->assertNotNull($phase->cycle->fresh()->deferred_at);
        $this->assertDatabaseCount('exploration_events', 0);
    }

    #[Group('query')]
    public function test_a_repeated_variant_probe_uses_the_current_plans_geometry(): void
    {
        $seed = $this->commit($this->commit(null, boundary: true));
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $context = new ParticipationContext($phase);
        $probe = fn () => app(PhaseGenerationService::class)->available($phase, 'proposition.variant', $context, prepared: true);
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $this->assertTrue($probe());
            $cold = count(DB::getQueryLog());
            DB::flushQueryLog();
            $this->assertTrue($probe());
            $this->assertCount(0, DB::getQueryLog());
            fwrite(STDERR, "\nVARIANT eligibility: {$cold} cold queries, 0 on repeat\n");
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_missing_geometry_or_birth_history_cannot_close_variant_admission(): void
    {
        $origin = $this->commit(null, boundary: true);
        $seed = $this->commit($this->commit($origin), -0.8);
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($phase->cycle)['closed']);
        DB::table('closure_births')->where('proposition_id', $seed->id)->delete();
        $this->assertSame('closure_history_unavailable', app(CrossoverMarginService::class)->inspect($phase->cycle)['unavailable_reason']);
        $this->assertTrue(app(AcquisitionAttemptService::class)->allows($phase, 'instrument.execute'));
        $seed->commitmentSnapshot->delete();
        $this->assertSame('committed_geometry_unavailable', app(CrossoverMarginService::class)->inspect($phase->cycle)['unavailable_reason']);
        $this->assertTrue(app(AcquisitionAttemptService::class)->allows($phase, 'instrument.execute'));
    }

    public function test_zero_cardinal_retains_canonical_birth_direction_without_reading_mutable_snapshot_direction(): void
    {
        $origin = $this->commit(null, boundary: true);
        $seed = $this->commit($origin, 0);
        $phase = $this->phase($seed, PhaseKind::Order);
        $before = DB::table('closure_births')->get()->toJson();
        foreach (['negative' => 0.0, 'positive' => 1.0] as $direction => $endpoint) {
            // Isolate the consumer at zero: hysteresis is owned by the birth service.
            $this->partialMock(ClosureBirthService::class, function ($mock) use ($direction): void {
                $mock->shouldReceive('prospective')->once()->andReturn([
                    'depth' => -1.0, 'committed_cardinal_position' => 0.0,
                    'committed_cardinal_position_direction' => $direction,
                ]);
            });
            $diagnostic = app(CrossoverMarginService::class)->inspect($phase->cycle);
            $this->assertSame($endpoint, $diagnostic['admissible_cadence_bound']);
            $this->assertEqualsWithDelta($endpoint / 6, $diagnostic['crossover_margin'], 1e-7);
            $this->assertSame($endpoint === 0.0, $diagnostic['closed']);
        }
        $this->assertSame($before, DB::table('closure_births')->get()->toJson());
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
        DB::table('closure_births')->where('proposition_id', $seed->id)->delete();
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
        // The existing continuation itself must terminate; unrelated catastrophes
        // cannot close it merely by reaching a witness count.
        $this->boundaryTragedyFixture($terminal->gate);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        app(ParticipationService::class)->contribute($terminal->cycle->participant, $terminal->id, ['action' => 'participant.rest']);
        $this->assertSame($parent->id, $terminal->cycle->participant->fresh()->current_proposition_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }
}
