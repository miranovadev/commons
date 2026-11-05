<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
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
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
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

    public static function committedValues(): array
    {
        return ['positive' => [0.2], 'negative' => [-0.8]];
    }

    #[DataProvider('committedValues')]
    public function test_genuine_rest_routes_only_the_resting_participant(float $value): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, $value);
        $sibling = $this->commit($parent, 0.99);
        $terminal = $this->terminal($seed);
        $other = $this->phase($seed, PhaseKind::Distinguish);
        app(PotentialProjectionService::class)->refresh($this->substrate->id);
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
        $this->assertFalse($plan['rest_available']);
        $this->assertArrayHasKey('instrument.execute', $plan['schema']['$defs']['actions']);
        $this->assertSame($seed->id, $other->cycle->fresh()->entry_committal_id);
    }

    public function test_a_new_opportunity_after_rest_cannot_displace_an_authorised_backward_destination(): void
    {
        $parent = $this->commit(null, boundary: true);
        $seed = $this->commit($parent, -0.8);
        $phase = $this->phase($seed, PhaseKind::Distinguish);
        $actor = $phase->cycle->participant;
        $this->publishPotentialFixture($this->substrate);
        // Historical Rest at the entry predates retirement. It still authorizes a strict backward hop.
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now(), 'rest_reason' => 'no_remaining_potential'])->save();
        $write = $this->node($this->phase($seed, PhaseKind::Distinguish));
        $this->receiptFixture($write);
        $this->publishPotentialFixture($this->substrate);
        $state = app(ParticipationService::class)->prepare($actor);
        $this->assertSame($parent->id, $state['current_proposition_id']);
        $this->assertSame($seed->id, $phase->cycle->fresh()->entry_committal_id);
    }

    public function test_boundary_reopens_generation_while_hard_superseded_origin_waits_in_its_horizon(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $boundary = $this->commit($parent, 0.8, boundary: true);
        $terminal = $this->terminal($boundary);
        $this->assertSame('emergence', $terminal->cycle->contextHorizon->domain->value);
        $atOrigin = $this->terminal($origin);

        foreach ([$terminal, $atOrigin] as $phase) {
            $actor = $phase->cycle->participant;
            $service = app(ParticipationService::class);
            $entry = $service->contribute($actor, $phase->id, ['action' => 'participant.rest']);
            $this->assertSame($phase->cycle->entry_committal_id, $entry['current_proposition_id']);
            $actions = array_keys($entry['plan']['schema']['$defs']['actions']);
            if ($phase->is($atOrigin)) {
                $this->assertSame(['participant.rest'], $actions);
                $count = $actor->cycles()->count();
                $waiting = $service->contribute($actor, $entry['plan']['phase']['id'], ['action' => 'participant.rest']);
                $this->assertSame('recovery_unavailable', $waiting['reason']['code']);
                $this->assertSame('waiting', $service->prepare($actor)['status']);
                $this->assertSame($count, $actor->cycles()->count());
            } else {
                $this->assertSame(['instrument.execute'], $actions);
            }
            $this->assertSame($phase->cycle->context_horizon_id, $entry['cycle']['context_horizon_id']);
            $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($phase->cycle->contextHorizon));
            $this->assertSame($phase->is($atOrigin) ? 2 : 1, $actor->cycles()->whereNotNull('rested_at')->count());
        }
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_two_participants_finish_historical_siblings_independently_before_fresh_generation(): void
    {
        $parent = $this->commit(null, boundary: true);
        $seed = $this->commit($parent, -0.8);
        // From entry onward each judgment, Traverse and Rest uses participation.
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
        $this->assertFalse($finished['plan']['rest_available']);
        $this->assertArrayHasKey('proposition.traverse', $finished['plan']['schema']['$defs']['actions']);
        $this->assertDatabaseCount('exploration_events', 3);
        foreach ($route as $target) {
            $unfinished = $act($luna, 'proposition.traverse', $target);
        }
        $this->assertEqualsCanonicalizing([$left->id, $right->id], $unfinished['plan']['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum']);
        $this->assertFalse(collect($unfinished['plan']['write_options'])->firstWhere('id', $right->id)['explored']);
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
    public function test_negative_committed_values_preserve_acquisition_and_resolution_in_each_generative_phase(PhaseKind $kind): void
    {
        $origin = $this->commit(null, boundary: true);
        $seed = $this->commit($this->commit($origin), -0.8);
        $gate = $kind === PhaseKind::Distinguish ? $seed
            : $this->node($this->phase($seed, PhaseKind::from($kind->value - 1)));
        $phase = $this->phase($seed, $kind, $gate);
        $attempts = app(AcquisitionAttemptService::class);
        $generation = app(PhaseGenerationService::class);
        $this->assertSame('required', $attempts->state($phase)['status']);
        $this->assertTrue($attempts->allows($phase, 'instrument.execute'));
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $this->instrumentGateFixture($phase);
        $this->assertTrue($generation->available($phase, 'proposition.variant'));
        $execution = $attempts->execution($phase);

        $other = $this->phase($seed, $kind, $gate);
        $this->assertSame('required', $attempts->state($other)['status']);
        $this->assertTrue($attempts->allows($other, 'instrument.execute'));
        $this->assertFalse($attempts->allows($phase, 'instrument.execute'));
        $this->assertEqualsCanonicalizing(['acquisition.defer', 'proposition.variant'],
            array_keys($attempts->schema($phase, $execution)['$defs']['actions']));
        $count = Proposition::count();
        $attempts->resolve($phase->cycle->participant, $execution, ['action' => 'acquisition.defer',
            'execution_id' => $execution->id, 'reason' => 'Defer this generative opportunity.']);
        $this->assertSame($count, Proposition::count());
        $this->assertNotNull($phase->cycle->fresh()->deferred_at);
        $this->assertDatabaseCount('exploration_events', 0);
    }

    #[Group('query')]
    public function test_variant_probes_do_not_read_archived_births_and_repeat_without_queries(): void
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
            $this->assertStringNotContainsString('closure_births', implode("\n", array_column(DB::getQueryLog(), 'query')));
            DB::flushQueryLog();
            $this->assertTrue($probe());
            $this->assertCount(0, DB::getQueryLog());
            fwrite(STDERR, "\nVARIANT eligibility: {$cold} cold queries, 0 on repeat\n");
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_reinforcement_preserves_committed_measurements(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, 0);
        $phase = $this->phase($seed, PhaseKind::Order);
        $before = $seed->commitmentSnapshot->getAttributes();
        $this->loadedReceiptFixture($seed);
        $state = fn () => DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson();
        $measurements = $state();
        $this->assertSame(1, app(ReinforcementService::class)->count($seed));
        $this->assertSame($before, $seed->fresh()->commitmentSnapshot->getAttributes());
        $this->assertSame($measurements, $state());
        $this->loadedReceiptFixture($seed);
        $measurements = $state();
        $this->assertSame(2, app(ReinforcementService::class)->count($seed));
        $this->assertSame($before, $seed->fresh()->commitmentSnapshot->getAttributes());
        $this->assertSame($measurements, $state());
    }

    public function test_authoritative_exhaustion_still_skips_an_exhausted_region(): void
    {
        $origin = $this->commit(null, boundary: true);
        $parent = $this->commit($origin);
        $seed = $this->commit($parent, 0.2);
        $terminal = $this->terminal($seed);
        // The existing continuation itself must terminate; unrelated catastrophes
        // cannot close it merely by reaching a witness count.
        $entry = $this->phase($seed, PhaseKind::Distinguish);
        $this->boundaryTragedyFixture($terminal->gate);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertFalse(app(AcquisitionAttemptService::class)->allows($entry, 'instrument.execute'));
        app(ParticipationService::class)->contribute($terminal->cycle->participant, $terminal->id, ['action' => 'participant.rest']);
        $this->assertSame($parent->id, $terminal->cycle->participant->fresh()->current_proposition_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }
}
