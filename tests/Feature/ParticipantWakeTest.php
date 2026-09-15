<?php

namespace Tests\Feature;

use App\Contracts\ParticipantInference;
use App\Contracts\WebSearchProvider;
use App\Domain\Evidence\SearchObservation;
use App\Domain\Observables\ClosureValues;
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
use App\Services\ClosureGateService;
use App\Services\CycleEntryService;
use App\Services\InputSchemaService;
use App\Services\LedgerService;
use App\Services\OpenAIResponseSchema;
use App\Services\OpenAIResponsesClient;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantActivityService;
use App\Services\ParticipationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Fixtures\ParticipantContextFixture;
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
                $now->addMinutes(15),
            ),
        );

        $this->artisan('commons:participants-wake-due')
            ->expectsOutput('Queued 0 Participant wake job(s).')
            ->assertSuccessful();
        Queue::assertPushed(WakeParticipant::class, 1);
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

            $this->assertSame(
                ['status' => $state['status'], 'plan' => $state['plan']],
                json_decode(
                    $payload['input'][1]['content'],
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
            );
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

    public function test_wake_job_action_ceiling_leaves_the_cycle_active(): void
    {
        config()->set('participation.max_actions_per_wake', 1);
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
        config()->set('participation.max_actions_per_wake', 1);
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

    public function test_one_wake_reads_fresh_state_through_a_whole_cycle_and_stops_at_prepared_phase_one(): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $f->phase->forceFill(['kind' => PhaseKind::Distinguish, 'gate_proposition_id' => $f->seed->id, 'write_register' => 'activity'])->save();
        $nodes = [];
        $parent = $f->seed;
        foreach (['activity', 'frontier', 'canon', 'activity', 'frontier', 'canon'] as $register) {
            $node = $f->node($parent, $register);
            $f->evidence($node);
            $nodes[] = $node;
            $parent = $node;
        }
        $closure = [...(new ClosureValues(1, 1))->resolve(), 'history_proposition_id' => null, 'history_source' => 'negative-default'];
        $this->mock(ClosureGateService::class, fn ($mock) => $mock->shouldReceive('preview', 'forCommitment')->andReturn($closure));
        $this->app->instance(WebSearchProvider::class, new class implements WebSearchProvider
        {
            public function search(string $query): SearchObservation
            {
                return new SearchObservation($query, CarbonImmutable::now(), [], 'test');
            }
        });
        $receipt = app(OperationalInstrumentService::class)->execute($f->phase, $nodes[0], ['kind' => 'web_search', 'query' => 'wake evidence']);
        $connection = $this->connectionFor($f->viewer);
        $cycleId = $f->phase->cycle_id;
        $calls = [];
        $read = false;
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->times(8)->andReturnUsing(function (Connection $connection, array $state) use (&$calls, &$read, $receipt, $nodes, $cycleId, $f): array {
            $activity = app(ParticipantActivityService::class)->snapshot(collect([$f->viewer->fresh()]))['participants'][0];
            $this->assertTrue($activity['is_active']);
            $this->assertNotNull($activity['current_proposition_id']);
            $this->assertSame($cycleId, $state['cycle']['id']);
            $kind = $state['plan']['phase']['number'];
            $calls[] = [$kind, $state['plan']['phase']['id']];
            if (! $read) {
                $this->assertSame('available', collect($state['plan']['receipts'])->firstWhere('id', $receipt->id)['status']);
                $read = true;

                return ['action' => 'receipt.read', 'receipt_id' => $receipt->id];
            }
            if ($kind === 1) {
                $this->assertSame('loaded', collect($state['plan']['receipts'])->firstWhere('id', $receipt->id)['status']);
            }

            return $kind === 7 ? ['action' => 'proposition.commit', 'proposition_id' => $nodes[5]->id]
                : ['action' => in_array($kind, [4, 5], true) ? 'attestation.create' : 'proposition.traverse',
                    'proposition_id' => $nodes[$kind - 1]->id, ...(in_array($kind, [4, 5], true) ? ['direction' => 'refute'] : [])];
        });
        $this->app->instance(ParticipantInference::class, $inference);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        $this->assertSame([1, 1, 2, 3, 4, 5, 6, 7], array_column($calls, 0));
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

        // A separately scheduled wake resumes the exact prepared occurrence.
        config()->set('participation.max_actions_per_wake', 1);
        $second = Mockery::mock(ParticipantInference::class);
        $second->shouldReceive('contribute')->once()->withArgs(fn ($connection, $state) => $state['plan']['phase']['id'] === $nextPhaseId)
            ->andReturn(['action' => 'proposition.variant', 'statement' => 'The next wake resumes Phase 1.']);
        $this->app->instance(ParticipantInference::class, $second);
        $this->app->call([new WakeParticipant($connection->id), 'handle']);
        $this->assertNotNull(Phase::findOrFail($nextPhaseId)->completed_at);
    }

    public function test_stale_inference_rebuilds_state_and_counts_towards_the_operational_ceiling(): void
    {
        config()->set('participation.max_actions_per_wake', 2);
        $f = new ParticipantContextFixture(1, 0);
        $target = $f->node($f->gate, 'frontier');
        $connection = $this->connectionFor($f->viewer);
        $calls = 0;
        $input = ['kind' => 'citation', 'reference' => $f->seed->id];
        $inference = Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->twice()->andReturnUsing(function ($connection, $state) use (&$calls, $f, $target, $input): array {
            $calls++;
            if ($calls === 1) {
                app(OperationalInstrumentService::class)->execute($f->phase, $target, $input);
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
