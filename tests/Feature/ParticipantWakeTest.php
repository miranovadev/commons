<?php

namespace Tests\Feature;

use App\Contracts\ParticipantInference;
use App\Contracts\WebSearchProvider;
use App\Domain\Evidence\SearchObservation;
use App\Enums\PhaseKind;
use App\Jobs\WakeParticipant;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Tragedy;
use App\Services\CycleEntryService;
use App\Services\DueParticipantWakeDispatcher;
use App\Services\InputSchemaService;
use App\Services\LedgerService;
use App\Services\OpenAIResponseSchema;
use App\Services\OpenAIResponsesClient;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantActivityService;
use App\Services\ParticipationService;
use App\Services\WakeClock;
use App\Services\WakeOffsetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\Support\RefreshIntegrationDatabase as RefreshDatabase;
use Tests\TestCase;

class ParticipantWakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('schemas.cache.store', 'array');
        Cache::store('array')->clear();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_scheduler_command_claims_due_connections_and_queues_wakes(): void
    {
        Queue::fake();
        $now = CarbonImmutable::parse('2026-09-07 10:00:00 UTC');
        CarbonImmutable::setTestNow($now);
        [, $participantConnection] = $this->readyConnection(
            nextTriggerAt: $now->subMinute(),
        );

        $this->artisan('commons:participants-wake-due')
            ->expectsOutput('Queued 1 Participant wake job(s).')
            ->assertSuccessful();

        Queue::assertPushedOn(
            'participants',
            WakeParticipant::class,
            fn (WakeParticipant $job): bool => $job->participantConnectionId
                === $participantConnection->getKey(),
        );

        $participantConnection->refresh();
        $this->assertTrue($participantConnection->last_triggered_at->equalTo($now));
        $this->assertTrue(
            $participantConnection->next_trigger_at->equalTo(
                $now->subMinute()->addMinutes(15),
            ),
        );

        $this->artisan('commons:participants-wake-due')
            ->expectsOutput('Queued 0 Participant wake job(s).')
            ->assertSuccessful();
        Queue::assertPushed(WakeParticipant::class, 1);
    }

    public function test_overlapping_wake_does_not_burn_queue_attempts_and_a_later_wake_can_run(): void
    {
        [, $connection] = $this->readyConnection();
        $wake = new WakeParticipant($connection->id);
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldNotReceive('release');
        $wake->setJob($queueJob);
        $middleware = $wake->middleware()[0];
        $lock = Cache::lock($middleware->getLockKey($wake), 60);
        $this->assertTrue($lock->get());
        $ran = false;
        try {
            $middleware->handle($wake, function () use (&$ran): void {
                $ran = true;
            });
            $this->assertFalse($ran);
        } finally {
            $lock->release();
        }
        $middleware->handle($wake, function () use (&$ran): void {
            $ran = true;
        });
        $this->assertTrue($ran);
    }

    public function test_openai_receives_only_the_autonomous_context_as_json_and_a_strict_response_schema(): void
    {
        config()->set('services.openai.base_url', 'https://api.openai.test/v1');
        Http::fake([
            'api.openai.test/*' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'contribution' => [
                                'action' => 'proposition.variant',
                                'statement' => 'A model-generated distinction.',
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $connection = new Connection;
        $connection->forceFill([
            'provider' => 'openai',
            'configuration' => [
                'model' => 'gpt-test',
                'instructions' => 'Preserve uncertainty.',
            ],
            'credentials' => ['api_key' => 'test-secret'],
        ]);
        $state = [
            'status' => 'ready',
            'cycle' => ['id' => 'cycle-id', 'sequence' => 1],
            'plan' => [
                'phase' => ['id' => 'phase-id', 'kind' => 'distinguish'],
                'receipts' => [],
                'schema' => app(InputSchemaService::class)
                    ->forPhase(PhaseKind::Distinguish),
            ],
            'perspective' => ['max_read_count' => 25],
            'reason' => null,
            'entry' => null,
        ];

        $contribution = app(OpenAIResponsesClient::class)
            ->contribute($connection, $state);

        $this->assertSame([
            'action' => 'proposition.variant',
            'statement' => 'A model-generated distinction.',
        ], $contribution);

        Http::assertSent(function (Request $request) use ($state): bool {
            $payload = $request->data();

            $this->assertEquals(
                ['status' => $state['status'], 'plan' => $state['plan']],
                json_decode(
                    $payload['input'][2]['content'],
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
            );
            $this->assertSame(['loaded_receipts' => []], json_decode($payload['input'][1]['content'], true));
            $this->assertStringContainsString(
                'Preserve uncertainty.',
                $payload['input'][0]['content'],
            );
            $this->assertSame(
                'json_schema',
                $payload['text']['format']['type'],
            );
            $this->assertTrue($payload['text']['format']['strict']);
            $this->assertSame(
                'object',
                $payload['text']['format']['schema']['type'],
            );
            $this->assertArrayHasKey(
                'contribution',
                $payload['text']['format']['schema']['properties'],
            );

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-secret');
        });
    }

    public function test_openai_schema_makes_optional_fields_required_and_nullable(): void
    {
        $schema = app(OpenAIResponseSchema::class)->fromCommons(
            app(InputSchemaService::class)->forPhase(PhaseKind::Persist),
        );
        $contribution = collect($schema['properties']['contribution']['anyOf'])
            ->first(fn (array $variant): bool => $variant['properties']['action']['enum'] === ['attestation.create']);

        $this->assertSame('string', $contribution['properties']['direction']['type']);
        $this->assertArrayNotHasKey('$defs', $schema);
        $this->assertContains('rationale', $contribution['required']);
        $this->assertSame(
            ['string', 'null'],
            $contribution['properties']['rationale']['type'],
        );
        $this->assertContains(
            'attestation.create',
            $contribution['properties']['action']['enum'],
        );
        $this->assertStringNotContainsString(
            'pattern',
            json_encode($schema, JSON_THROW_ON_ERROR),
        );
    }

    public function test_default_phase_wake_completes_one_phase_and_leaves_the_cycle_active(): void
    {
        // Isolate structural rules from a new Charge-boundary event.
        $this->resolveExpressiveFieldFixture(.8);
        [$participant, $participantConnection] = $this->readyConnection();
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')
            ->once()
            ->withArgs(function (Connection $connection, array $state): bool {
                return $connection->provider->value === 'openai'
                    && $state['status'] === 'ready'
                    && $state['plan']['phase']['kind'] === 'distinguish';
            })
            ->andReturn([
                'action' => 'proposition.variant',
                'statement' => 'An asynchronous distinction.',
            ]);
        $this->app->instance(ParticipantInference::class, $inference);

        $this->app->call([
            new WakeParticipant($participantConnection->getKey()),
            'handle',
        ]);

        $this->assertDatabaseHas('propositions', [
            'statement' => 'An asynchronous distinction.',
            'register' => 'activity',
            'tier' => 'expressive',
        ]);
        $this->assertDatabaseHas('phases', [
            'cycle_id' => $participant->cycles()->firstOrFail()->getKey(),
            'kind' => PhaseKind::Persist->value,
            'write_register' => 'frontier',
        ]);
        $this->assertNull($participantConnection->fresh()->last_error);
    }

    public function test_wake_job_does_not_call_a_provider_while_participant_is_waiting(): void
    {
        $participant = $this->participant();
        $participantConnection = $this->connectionFor($participant);
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldNotReceive('contribute');
        $this->app->instance(ParticipantInference::class, $inference);

        $this->app->call([
            new WakeParticipant($participantConnection->getKey()),
            'handle',
        ]);

        $this->assertSame(
            'Participant wake skipped: cycle_not_started.',
            $participantConnection->fresh()->last_error,
        );
    }

    public function test_wake_job_enters_an_established_substrate_via_potential(): void
    {
        $originParticipant = $this->participant();
        app(CycleEntryService::class)->enter($originParticipant, [
            'statement' => 'A measured Origin.',
            'attestation' => [
                'direction' => 'support',
                'rationale' => 'Grounded for the next participant.',
            ],
            'instrument' => [
                'kind' => 'hyperlink',
                'reference' => 'https://example.test/origin',
            ],
        ]);
        $origin = Proposition::query()
            ->where('statement', 'A measured Origin.')
            ->firstOrFail();
        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $originParticipant->substrate_id,
            'callsign' => 'potential-wake',
            'max_read_count' => 25,
            'max_token_count' => 10000,
        ]);
        $participant->save();
        $participantConnection = $this->connectionFor($participant);
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')
            ->once()
            ->withArgs(fn (Connection $connection, array $state): bool => $state['status'] === 'ready'
                && $state['plan']['gate_id'] === $origin->getKey())
            ->andReturn([
                'action' => 'proposition.variant',
                'statement' => 'The second participant is awake.',
            ]);
        $this->app->instance(ParticipantInference::class, $inference);

        $this->app->call([
            new WakeParticipant($participantConnection->getKey()),
            'handle',
        ]);

        $this->assertDatabaseHas('cycles', [
            'participant_id' => $participant->getKey(),
            'sequence' => 1,
        ]);
        $this->assertDatabaseHas('propositions', [
            'statement' => 'The second participant is awake.',
            'tier' => 'expressive',
        ]);
        $this->assertNull($participantConnection->fresh()->last_error);
    }

    public function test_cycle_wake_stops_at_prepared_phase_one(): void
    {
        $this->runCycleWake('cycle');
    }

    #[Group('integration')]
    public function test_rest_wake_crosses_a_real_commit_and_stops_immediately_after_genuine_rest(): void
    {
        $this->runCycleWake('rest');
    }

    private function runCycleWake(string $mode): void
    {
        $this->controlledClosureFixture(-1);
        $f = new ParticipantContextFixture(1, 0);
        $f->phase->forceFill(['kind' => PhaseKind::Distinguish, 'gate_proposition_id' => $f->seed->id, 'write_register' => 'activity'])->save();
        $nodes = [];
        $parent = $f->seed;
        foreach (['activity', 'frontier', 'canon', 'activity', 'frontier', 'canon'] as $offset => $register) {
            $node = $f->node($parent, $register, position: PhaseKind::from($offset + 1));
            $f->evidence($node);
            $nodes[] = $node;
            $parent = $node;
        }
        $this->controlledBirthClosureFixture(1.0, includeCommittal: true);
        $this->app->instance(WebSearchProvider::class, new class implements WebSearchProvider
        {
            public function search(string $query): SearchObservation
            {
                return new SearchObservation($query, CarbonImmutable::now(), [], 'test');
            }
        });
        $receipt = app(OperationalInstrumentService::class)->execute($f->phase, $nodes[0], ['kind' => 'web_search', 'query' => 'wake evidence']);
        $this->publishPotentialFixture($f->viewer);
        $connection = $this->connectionFor($f->viewer);
        $connection->forceFill(['configuration' => array_replace_recursive($connection->configuration, ['wake' => ['termination' => $mode]])])->save();
        $cycleId = $f->phase->cycle_id;
        $calls = [];
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->times($mode === 'rest' ? 10 : 7)->andReturnUsing(function (Connection $connection, array $state) use (&$calls, $receipt, $nodes, $cycleId, $f): array {
            $activity = app(ParticipantActivityService::class)->snapshot(collect([$f->viewer->fresh()]))['participants'][0];
            $this->assertTrue($activity['is_active']);
            $this->assertNotNull($activity['current_proposition_id']);
            if ($state['cycle']['id'] !== $cycleId) {
                $calls[] = [$state['plan']['phase']['number'], $state['plan']['phase']['id']];
                if ($state['plan']['phase']['number'] <= 2) {
                    return ['action' => 'proposition.variant', 'statement' => 'The Rest wake continues into its second Cycle.'];
                }
                $this->assertArrayHasKey('participant.rest', $state['plan']['schema']['$defs']['actions']);

                return ['action' => 'participant.rest'];
            }
            $kind = $state['plan']['phase']['number'];
            $calls[] = [$kind, $state['plan']['phase']['id']];
            if ($kind === 1) {
                $this->assertFalse(collect($state['plan']['receipts'])->firstWhere('id', $receipt->id)['loaded']);
            }
            if ($kind === 3) {
                $this->assertTrue(collect($state['plan']['receipts'])->firstWhere('id', $receipt->id)['loaded']);
            }

            return $kind === 7 ? ['action' => 'proposition.commit', 'proposition_id' => $nodes[5]->id]
                : ['action' => in_array($kind, [4, 5], true) ? 'attestation.create' : 'proposition.traverse',
                    'proposition_id' => $nodes[$kind - 1]->id, ...($kind === 2 ? ['receipt_options' => ['load' => [$receipt->id]]] : []), ...(in_array($kind, [4, 5], true) ? ['direction' => 'refute'] : [])];
        });
        $this->app->instance(ParticipantInference::class, $inference);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        if ($mode === 'rest') {
            $this->assertSame([1, 2, 3, 4, 5, 6, 7, 1, 2, 3], array_column($calls, 0));
            $this->assertCount(10, array_unique(array_column($calls, 1)));
            $this->assertNotNull(Cycle::findOrFail($cycleId)->completed_at);
            $this->assertNull(Cycle::findOrFail($cycleId)->rested_at);
            $this->assertNotNull(Phase::findOrFail($calls[9][1])->cycle->rested_at);

            return;
        }
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_column($calls, 0));
        $this->assertCount(7, array_unique(array_column($calls, 1)));
        $this->assertNotNull(Cycle::findOrFail($cycleId)->completed_at);
        $this->assertNull(Cycle::findOrFail($cycleId)->rested_at);
        $next = app(ParticipationService::class)->stateFor($f->viewer);
        $this->assertSame('ready', $next['status']);
        $this->assertSame('distinguish', $next['plan']['phase']['kind']);
        $this->assertNotSame($cycleId, $next['cycle']['id']);
        $nextPhaseId = $next['plan']['phase']['id'];
        $activity = app(ParticipantActivityService::class)->snapshot(collect([$f->viewer->fresh()]))['participants'][0];
        $this->assertFalse($activity['is_active']);
        $this->assertSame($next['plan']['gate_id'], $activity['current_proposition_id']);
        $this->assertSame(1, Phase::where('cycle_id', $next['cycle']['id'])->count());

        // A separately scheduled Phase wake resumes the exact prepared occurrence.
        $connection->forceFill(['configuration' => array_replace_recursive($connection->configuration, ['wake' => ['termination' => 'phase']])])->save();
        $second = Mockery::mock(ParticipantInference::class);
        $second->shouldReceive('contribute')->once()->withArgs(fn ($connection, $state) => $state['plan']['phase']['id'] === $nextPhaseId)
            ->andReturn(['action' => 'proposition.variant', 'statement' => 'The next wake resumes Phase 1.']);
        $this->app->instance(ParticipantInference::class, $second);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        $this->assertNotNull(Phase::findOrFail($nextPhaseId)->completed_at);
    }

    public function test_stale_inference_rebuilds_state_without_replaying_a_rejected_contribution(): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $target = $f->node($f->gate, 'frontier', position: PhaseKind::Mediate);
        $this->publishPotentialFixture($f->viewer);
        $connection = $this->connectionFor($f->viewer);
        $calls = 0;
        $input = ['kind' => 'citation', 'reference' => $f->seed->id];
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->twice()->andReturnUsing(function ($connection, $state) use (&$calls, $f, $target, $input): array {
            $calls++;
            if ($calls === 1) {
                app(OperationalInstrumentService::class)->execute($f->phase, $target, $input);
                $this->publishPotentialFixture($f->viewer);
            } else {
                $this->assertSame($input, $state['plan']['schema']['x-commons']['instrument_exclusions'][$target->id][0]['invocation']);
                $this->assertSame($f->phase->id, $state['plan']['phase']['id']);
            }

            return ['action' => 'instrument.execute', 'proposition_id' => $target->id,
                'instrument' => $calls === 1 ? $input : ['kind' => 'citation', 'reference' => $f->gate->id]];
        });
        $this->app->instance(ParticipantInference::class, $inference);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        $this->assertSame(2, Receipt::where('receiptable_id', $target->id)->count());
        $this->assertNull($f->phase->cycle->fresh()->completed_at);
        $this->assertNull($f->phase->cycle->fresh()->rested_at);
        $this->assertSame('bias', app(ParticipationService::class)->stateFor($f->viewer)['plan']['phase']['kind']);
        $this->assertNull($connection->fresh()->last_error);
    }

    public function test_inference_failure_clears_activity_and_preserves_canonical_position(): void
    {
        [$participant, $connection] = $this->readyConnection();
        $participant->forceFill(['current_proposition_id' => Proposition::whereHas('phase.cycle', fn ($q) => $q->where('participant_id', $participant->id))->value('id')])->save();
        $position = $participant->current_proposition_id;
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->once()->andReturnUsing(function () use ($participant): array {
            $this->assertTrue(app(ParticipantActivityService::class)->snapshot(collect([$participant->fresh()]))['participants'][0]['is_active']);
            throw new \RuntimeException('Inference failed');
        });
        $this->app->instance(ParticipantInference::class, $inference);
        try {
            $this->app->call([new WakeParticipant($connection->id), 'handle']);
            $this->fail('The wake should retain its retry behaviour.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Inference failed', $exception->getMessage());
        }
        $state = app(ParticipantActivityService::class)->snapshot(collect([$participant->fresh()]))['participants'][0];
        $this->assertFalse($state['is_active']);
        $this->assertSame($position, $state['current_proposition_id']);
    }

    public function test_cycle_mode_stops_when_the_current_cycle_rests(): void
    {
        $this->resolveExpressiveFieldFixture(.8);
        [$participant, $connection] = $this->readyConnection();
        $connection->forceFill(['configuration' => array_replace_recursive($connection->configuration, ['wake' => ['termination' => 'cycle']])])->save();
        $cycle = $participant->cycles()->firstOrFail();
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->times(3)->andReturn(
            ['action' => 'proposition.variant', 'statement' => 'A fresh distinction.'],
            ['action' => 'proposition.variant', 'statement' => 'A persisted source without classification evidence.'],
            ['action' => 'participant.rest'],
        );
        $this->app->instance(ParticipantInference::class, $inference);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        $this->assertNotNull($cycle->fresh()->rested_at);
        $this->assertNotNull($cycle->fresh()->completed_at);
    }

    public function test_whole_job_deadline_preserves_completed_work_and_next_wake_resumes(): void
    {
        $this->resolveExpressiveFieldFixture(.8);
        [$participant, $connection] = $this->readyConnection();
        $connection->forceFill(['configuration' => array_replace_recursive($connection->configuration, ['wake' => ['termination' => 'rest', 'timeout_seconds' => 600]])])->save();
        $cycle = $participant->cycles()->firstOrFail();
        $tragedies = Tragedy::count();
        $exhaustions = DB::table('commit_region_exhaustions')->count();
        $clock = Mockery::mock(WakeClock::class);
        $clock->shouldReceive('now')->andReturn(0, 0, 0, 0, 601);
        $this->app->instance(WakeClock::class, $clock);
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->once()->andReturn(['action' => 'proposition.variant', 'statement' => 'Completed before the wake deadline.']);
        $this->app->instance(ParticipantInference::class, $inference);
        $job = new WakeParticipant($connection->id);
        $this->assertSame(600, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->app->call([$job, 'handle']);
        $this->assertDatabaseHas('propositions', ['statement' => 'Completed before the wake deadline.']);
        $this->assertNull($cycle->fresh()->completed_at);
        $this->assertNull($cycle->fresh()->rested_at);
        $this->assertSame($tragedies, Tragedy::count());
        $this->assertSame($exhaustions, DB::table('commit_region_exhaustions')->count());
        $this->assertStringContainsString('timed out', $connection->fresh()->last_error);
        $phase = $cycle->phases()->whereNull('completed_at')->firstOrFail();
        $this->app->instance(WakeClock::class, new WakeClock);
        $resume = Mockery::mock(ParticipantInference::class);
        $resume->shouldReceive('contribute')->twice()->andReturnUsing(function ($connection, $state) use ($phase): array {
            if ($state['plan']['phase']['id'] === $phase->id) {
                return ['action' => 'proposition.variant', 'statement' => 'Resumed without replaying the distinction.'];
            }
            $this->assertArrayHasKey('participant.rest', $state['plan']['schema']['$defs']['actions']);

            return ['action' => 'participant.rest'];
        });
        $this->app->instance(ParticipantInference::class, $resume);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        $this->assertNotNull($cycle->fresh()->rested_at);
        $this->assertNull($connection->fresh()->last_error);
    }

    public function test_expired_inference_is_not_contributed(): void
    {
        [$participant, $connection] = $this->readyConnection();
        $clock = Mockery::mock(WakeClock::class);
        $clock->shouldReceive('now')->andReturn(0, 0, 0, 601);
        $this->app->instance(WakeClock::class, $clock);
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->once()->andReturn(['action' => 'proposition.variant', 'statement' => 'Too late.']);
        $this->app->instance(ParticipantInference::class, $inference);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        $this->assertDatabaseMissing('propositions', ['statement' => 'Too late.']);
        $this->assertNull($participant->cycles()->firstOrFail()->completed_at);
    }

    public function test_fixed_offsets_use_native_queue_availability_without_cadence_drift(): void
    {
        Queue::fake();
        $at = CarbonImmutable::parse('2026-09-07 12:00:00 UTC');
        CarbonImmutable::setTestNow($at);
        [, $connection] = $this->readyConnection($at);
        foreach ([0, 10, 60] as $seconds) {
            $connection->forceFill(['configuration' => ['trigger' => ['mode' => 'interval', 'interval_minutes' => 1, 'offset' => ['mode' => 'seconds', 'seconds' => $seconds]]]])->save();
            $this->assertSame(1, app(DueParticipantWakeDispatcher::class)->dispatch());
            Queue::assertPushed(WakeParticipant::class, fn ($job) => $job->delay->equalTo($at->addSeconds($seconds)));
            $this->assertTrue($connection->fresh()->next_trigger_at->equalTo($at->addMinute()));
            $at = $at->addMinute();
            CarbonImmutable::setTestNow($at);
        }
    }

    public function test_late_scheduler_claims_preserve_scheduled_offsets_and_cadence(): void
    {
        $scheduledAt = CarbonImmutable::parse('2026-09-07 12:00:00 UTC');
        CarbonImmutable::setTestNow($scheduledAt);
        [, $connection] = $this->readyConnection($scheduledAt);
        $connection->forceFill(['configuration' => ['trigger' => ['mode' => 'interval', 'interval_minutes' => 1,
            'offset' => ['mode' => 'seconds', 'seconds' => 10]]]])->save();

        // Cover claims before and after queue availability, without accumulating lateness.
        foreach ([7, 40, 19] as $lateSeconds) {
            Queue::fake();
            $claimedAt = $scheduledAt->addSeconds($lateSeconds);
            CarbonImmutable::setTestNow($claimedAt);

            $this->assertSame(1, app(DueParticipantWakeDispatcher::class)->dispatch());
            Queue::assertPushed(WakeParticipant::class, fn ($job) => $job->participantConnectionId === $connection->id
                && $job->delay->equalTo($scheduledAt->addSeconds(10)));
            $connection->refresh();
            $this->assertTrue($connection->last_triggered_at->equalTo($claimedAt));
            $this->assertTrue($connection->next_trigger_at->equalTo($scheduledAt->addMinute()));

            $this->assertSame(0, app(DueParticipantWakeDispatcher::class)->dispatch());
            Queue::assertPushed(WakeParticipant::class, 1);
            $scheduledAt = $scheduledAt->addMinute();
        }
    }

    public function test_random_offsets_are_sampled_per_trigger_and_never_move_the_schedule(): void
    {
        Queue::fake();
        $at = CarbonImmutable::parse('2026-09-07 12:00:00 UTC');
        CarbonImmutable::setTestNow($at);
        [, $connection] = $this->readyConnection($at);
        $connection->forceFill(['configuration' => ['trigger' => ['mode' => 'interval', 'interval_minutes' => 1, 'offset' => ['mode' => 'random', 'random_from' => 30, 'random_to' => 40]]]])->save();
        $resolver = new class extends WakeOffsetResolver
        {
            public array $samples = [30, 40];

            protected function sample(int $from, int $to): int
            {
                if ($from !== 30 || $to !== 40) {
                    throw new \RuntimeException('Unexpected random offset bounds.');
                }

                return array_shift($this->samples);
            }
        };
        $this->app->instance(WakeOffsetResolver::class, $resolver);
        foreach ([30, 40] as $seconds) {
            $this->assertSame(1, app(DueParticipantWakeDispatcher::class)->dispatch());
            Queue::assertPushed(WakeParticipant::class, fn ($job) => $job->delay->equalTo($at->addSeconds($seconds)));
            $this->assertTrue($connection->fresh()->next_trigger_at->equalTo($at->addMinute()));
            $at = $at->addMinute();
            CarbonImmutable::setTestNow($at);
        }
        $this->assertSame([], $resolver->samples);
        $this->assertArrayNotHasKey('sample', $connection->fresh()->configuration['trigger']['offset']);
    }

    public function test_delayed_rest_wakes_share_a_participant_lock_but_other_participants_do_not(): void
    {
        [$participant, $connection] = $this->readyConnection();
        $connection->forceFill(['configuration' => array_replace_recursive($connection->configuration, ['wake' => ['termination' => 'rest', 'timeout_seconds' => 1800]])])->save();
        $first = new WakeParticipant($connection->id);
        $sameParticipant = (new WakeParticipant($this->connectionFor($participant)->id))->delay(60);
        [, $otherConnection] = $this->readyConnection();
        $other = (new WakeParticipant($otherConnection->id))->delay(30);
        $lock = Cache::lock($first->middleware()[0]->getLockKey($first), $first->timeout + 30);
        $this->assertTrue($lock->get());
        $sameRan = false;
        $otherRan = false;
        try {
            $sameParticipant->middleware()[0]->handle($sameParticipant, function () use (&$sameRan): void {
                $sameRan = true;
            });
            $other->middleware()[0]->handle($other, function () use (&$otherRan): void {
                $otherRan = true;
            });
            $this->assertFalse($sameRan);
            $this->assertTrue($otherRan);
        } finally {
            $lock->release();
        }
    }

    /** @return array{Participant, ParticipantConnection} */
    private function readyConnection(
        ?CarbonImmutable $nextTriggerAt = null,
    ): array {
        $participant = $this->participant();
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 1,
        ]);
        $cycle->save();
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Distinguish,
            'write_register' => 'activity',
            'started_at' => now(),
        ]);
        $phase->save();
        $gate = new Proposition;
        $gate->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => 'canon',
            'tier' => 'committal',
            'statement' => 'The current Gate.',
        ]);
        $gate->save();
        app(LedgerService::class)->record($gate);
        $phase->forceFill(['gate_proposition_id' => $gate->getKey()])->save();
        $this->publishPotentialFixture($participant);

        return [
            $participant,
            $this->connectionFor($participant, $nextTriggerAt),
        ];
    }

    private function participant(): Participant
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'wake-'.str()->lower(str()->random(8)),
            'title' => 'Wake tests',
            'visibility' => 'public',
            'configuration' => ['maximum_participants' => 10],
        ]);
        $substrate->save();
        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'mira',
            'max_read_count' => 25,
            'max_token_count' => 10000,
        ]);
        $participant->save();

        return $participant;
    }

    private function connectionFor(
        Participant $participant,
        ?CarbonImmutable $nextTriggerAt = null,
    ): ParticipantConnection {
        $connection = new Connection;
        $connection->forceFill([
            'provider' => 'openai',
            'name' => 'OpenAI '.str()->random(8),
            'configuration' => ['model' => 'gpt-test'],
            'credentials' => ['api_key' => 'test-secret'],
        ]);
        $connection->save();
        $participantConnection = new ParticipantConnection;
        $participantConnection->forceFill([
            'participant_id' => $participant->getKey(),
            'connection_id' => $connection->getKey(),
            'is_enabled' => true,
            'configuration' => [
                'trigger' => [
                    'mode' => 'interval',
                    'interval_minutes' => 15,
                ],
            ],
            'next_trigger_at' => $nextTriggerAt ?? now(),
        ]);
        $participantConnection->save();

        return $participantConnection;
    }
}
