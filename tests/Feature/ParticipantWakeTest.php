<?php

namespace Tests\Feature;

use App\Contracts\ParticipantInference;
use App\Enums\PhaseKind;
use App\Jobs\WakeParticipant;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\CycleEntryService;
use App\Services\InputSchemaService;
use App\Services\LedgerService;
use App\Services\OpenAIResponseSchema;
use App\Services\OpenAIResponsesClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
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

    public function test_wake_job_requests_and_applies_one_contribution(): void
    {
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
