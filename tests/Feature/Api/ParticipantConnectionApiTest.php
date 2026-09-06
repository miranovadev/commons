<?php

namespace Tests\Feature\Api;

use App\Models\Connection;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Substrate;
use App\Models\User;
use App\Services\ParticipantConnectionTriggerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipantConnectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_reusable_connection_without_exposing_credentials(): void
    {
        $owner = $this->environment();
        $this->actingAs($owner);

        $response = $this->postJson('/api/connections', [
            'provider' => 'openai',
            'name' => 'Primary reasoning connection',
            'configuration' => [
                'model' => 'example-model',
                'instructions' => 'Participate carefully.',
            ],
            'credentials' => ['api_key' => 'connection-secret'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.configuration.model', 'example-model')
            ->assertJsonPath('data.has_credentials', true)
            ->assertJsonMissing(['credentials']);

        $connection = Connection::query()->firstOrFail();
        $storedCredentials = DB::table('connections')
            ->where('id', $connection->getKey())
            ->value('credentials');

        $this->assertSame('connection-secret', $connection->credentials['api_key']);
        $this->assertStringNotContainsString('connection-secret', $storedCredentials);
    }

    public function test_same_connection_can_be_reused_with_independent_wake_schedules(): void
    {
        $owner = $this->environment();
        $this->actingAs($owner);
        $connection = $this->createConnection();
        [$firstSubstrate, $firstParticipant] = $this->createParticipant($owner, 'first-space', 'mira');
        [$secondSubstrate, $secondParticipant] = $this->createParticipant($owner, 'second-space', 'mira');

        $firstResponse = $this->postJson(
            $this->connectionsUrl($firstSubstrate, $firstParticipant),
            [
                'connection_id' => $connection->getKey(),
                'configuration' => [
                    'trigger' => [
                        'mode' => 'interval',
                        'interval_minutes' => 15,
                    ],
                ],
            ],
        )->assertCreated();

        $secondResponse = $this->postJson(
            $this->connectionsUrl($secondSubstrate, $secondParticipant),
            [
                'connection_id' => $connection->getKey(),
                'configuration' => [
                    'trigger' => [
                        'mode' => 'interval',
                        'interval_minutes' => 60,
                    ],
                ],
            ],
        )->assertCreated();

        $firstResponse
            ->assertJsonPath('data.connection.id', $connection->getKey())
            ->assertJsonPath('data.configuration.trigger.interval_minutes', 15);
        $secondResponse
            ->assertJsonPath('data.connection.id', $connection->getKey())
            ->assertJsonPath('data.configuration.trigger.interval_minutes', 60);

        $links = ParticipantConnection::query()
            ->where('connection_id', $connection->getKey())
            ->orderBy('participant_id')
            ->get();

        $this->assertCount(2, $links);
        $this->assertSame(
            [15, 60],
            $links->pluck('configuration')
                ->map(fn (array $configuration): int => $configuration['trigger']['interval_minutes'])
                ->sort()
                ->values()
                ->all(),
        );
    }

    public function test_join_can_attach_existing_connection_with_substrate_specific_wake_settings(): void
    {
        $owner = $this->environment();
        $this->actingAs($owner);
        $connection = $this->createConnection();
        $substrate = $this->createSubstrate('join-with-connection');

        $participantId = $this->postJson(
            "/api/substrates/{$substrate->slug}/participants",
            [
                'callsign' => 'mira',
                'connection' => [
                    'id' => $connection->getKey(),
                    'configuration' => [
                        'trigger' => [
                            'mode' => 'interval',
                            'interval_minutes' => 30,
                        ],
                    ],
                ],
            ],
        )
            ->assertCreated()
            ->json('data.id');

        $link = ParticipantConnection::query()
            ->where('participant_id', $participantId)
            ->firstOrFail();

        $this->assertSame($connection->getKey(), $link->connection_id);
        $this->assertSame(30, $link->configuration['trigger']['interval_minutes']);
        $this->assertNotNull($link->next_trigger_at);
    }

    public function test_user_cannot_attach_another_users_connection(): void
    {
        $owner = $this->environment();
        $other = User::factory()->create();
        $this->actingAs($other);
        $foreignConnection = $this->createConnection();

        [$substrate, $participant] = $this->createParticipant($owner, 'ownership-test', 'mira');
        $this->actingAs($owner);

        $this->postJson(
            $this->connectionsUrl($substrate, $participant),
            ['connection_id' => $foreignConnection->getKey()],
        )->assertUnprocessable();
    }

    public function test_connection_credentials_can_be_kept_when_updating_configuration(): void
    {
        $owner = $this->environment();
        $this->actingAs($owner);
        $connection = $this->createConnection();

        $this->patchJson("/api/connections/{$connection->getKey()}", [
            'name' => 'Renamed connection',
            'configuration' => ['model' => 'new-model'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed connection')
            ->assertJsonPath('data.configuration.model', 'new-model')
            ->assertJsonMissing(['credentials']);

        $this->assertSame(
            'connection-secret',
            $connection->fresh()->credentials['api_key'],
        );
    }

    public function test_attached_connection_must_be_detached_before_deletion(): void
    {
        $owner = $this->environment();
        $this->actingAs($owner);
        $connection = $this->createConnection();
        [$substrate, $participant] = $this->createParticipant($owner, 'delete-test', 'mira');

        $linkId = $this->postJson(
            $this->connectionsUrl($substrate, $participant),
            ['connection_id' => $connection->getKey()],
        )
            ->assertCreated()
            ->json('data.id');

        $this->deleteJson("/api/connections/{$connection->getKey()}")
            ->assertConflict();

        $this->deleteJson(
            $this->connectionsUrl($substrate, $participant)."/{$linkId}",
        )->assertNoContent();

        $this->deleteJson("/api/connections/{$connection->getKey()}")
            ->assertNoContent();
    }

    public function test_interval_attachments_are_due_on_their_own_wall_clock_schedule(): void
    {
        $owner = $this->environment();
        $this->actingAs($owner);
        $connection = $this->createConnection();
        [, $participant] = $this->createParticipant($owner, 'clock-test', 'mira');
        $link = new ParticipantConnection;
        $link->forceFill([
            'participant_id' => $participant->getKey(),
            'connection_id' => $connection->getKey(),
            'is_enabled' => true,
            'configuration' => [
                'trigger' => [
                    'mode' => 'interval',
                    'interval_minutes' => 15,
                ],
            ],
        ]);
        $link->save();

        $clock = CarbonImmutable::parse('2026-09-07 10:00:00 UTC');
        $triggers = app(ParticipantConnectionTriggerService::class);
        $triggers->reschedule($link, $clock);
        $link->refresh();

        $this->assertTrue($link->next_trigger_at->equalTo($clock->addMinutes(15)));
        $this->assertFalse($triggers->isDue($link, $clock->addMinutes(14)));
        $this->assertTrue($triggers->isDue($link, $clock->addMinutes(15)));

        $triggers->recordTriggered($link, $clock->addMinutes(15));
        $link->refresh();

        $this->assertTrue($link->last_triggered_at->equalTo($clock->addMinutes(15)));
        $this->assertTrue($link->next_trigger_at->equalTo($clock->addMinutes(30)));
    }

    public function test_connection_options_describe_supported_providers_and_triggers(): void
    {
        $owner = $this->environment();
        $this->actingAs($owner);

        $this->getJson('/api/connection-options')
            ->assertOk()
            ->assertJsonPath('data.providers', ['openai', 'anthropic'])
            ->assertJsonPath('data.trigger_modes', ['interval', 'manual']);
    }

    private function environment(): User
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'Bootstrap owner',
            'email' => 'bootstrap@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();

        return User::factory()->create();
    }

    private function createConnection(): Connection
    {
        $id = $this->postJson('/api/connections', [
            'provider' => 'openai',
            'name' => 'Mira',
            'configuration' => ['model' => 'example-model'],
            'credentials' => ['api_key' => 'connection-secret'],
        ])
            ->assertCreated()
            ->json('data.id');

        return Connection::query()->findOrFail($id);
    }

    /** @return array{Substrate, Participant} */
    private function createParticipant(
        User $owner,
        string $slug,
        string $callsign,
    ): array {
        $substrate = $this->createSubstrate($slug);
        $this->actingAs($owner);
        $participantId = $this->postJson(
            "/api/substrates/{$substrate->slug}/participants",
            ['callsign' => $callsign],
        )
            ->assertCreated()
            ->json('data.id');

        return [
            $substrate,
            Participant::query()->findOrFail($participantId),
        ];
    }

    private function createSubstrate(string $slug): Substrate
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => $slug,
            'title' => str($slug)->headline()->toString(),
            'visibility' => 'public',
            'configuration' => ['maximum_participants' => 10],
        ]);
        $substrate->save();

        return $substrate;
    }

    private function connectionsUrl(
        Substrate $substrate,
        Participant $participant,
    ): string {
        return "/api/substrates/{$substrate->slug}"
            ."/participants/{$participant->getKey()}/connections";
    }
}
