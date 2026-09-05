<?php

namespace Tests\Feature\Api;

use App\Enums\ConnectionProvider;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Substrate;
use App\Models\User;
use App\Services\ParticipantConnectionTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipantConnectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_connection_without_exposing_credentials(): void
    {
        [$substrate, $participant] = $this->environment();

        $response = $this->postJson(
            $this->connectionsUrl($substrate, $participant),
            [
                'provider' => 'openai',
                'name' => 'Primary reasoning connection',
                'configuration' => [
                    'model' => 'example-model',
                    'trigger' => [
                        'mode' => 'cycle_interval',
                        'interval' => 3,
                    ],
                ],
                'credentials' => [
                    'api_key' => 'connection-secret',
                ],
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.has_credentials', true)
            ->assertJsonPath(
                'data.configuration.trigger.interval',
                3,
            )
            ->assertJsonMissing(['credentials']);

        $connection = ParticipantConnection::query()->firstOrFail();
        $storedCredentials = DB::table('participant_connections')
            ->where('id', $connection->getKey())
            ->value('credentials');

        $this->assertSame(
            'connection-secret',
            $connection->credentials['api_key'],
        );
        $this->assertStringNotContainsString(
            'connection-secret',
            $storedCredentials,
        );
    }

    public function test_owner_can_update_trigger_configuration_without_resending_secrets(): void
    {
        [$substrate, $participant] = $this->environment();
        $connection = $this->connection($participant);

        $this->patchJson(
            $this->connectionUrl($substrate, $participant, $connection),
            [
                'is_enabled' => false,
                'configuration' => [
                    'trigger' => [
                        'mode' => 'manual',
                    ],
                ],
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath(
                'data.configuration.trigger.mode',
                'manual',
            )
            ->assertJsonMissingPath(
                'data.configuration.trigger.interval',
            );

        $this->assertSame(
            'connection-secret',
            $connection->fresh()->credentials['api_key'],
        );
    }

    public function test_another_user_cannot_manage_the_participant_connection(): void
    {
        [$substrate, $participant] = $this->environment();
        $other = User::factory()->create();

        $this->actingAs($other)->postJson(
            $this->connectionsUrl($substrate, $participant),
            [
                'provider' => 'anthropic',
                'name' => 'Not mine',
                'configuration' => ['model' => 'example-model'],
                'credentials' => ['api_key' => 'not-allowed'],
            ],
        )->assertForbidden();
    }

    public function test_cycle_interval_connections_are_due_at_the_configured_frequency(): void
    {
        [, $participant] = $this->environment();
        $connection = $this->connection($participant, [
            'trigger' => [
                'mode' => 'cycle_interval',
                'interval' => 2,
            ],
        ]);
        $first = $this->cycle($participant, 1);
        $second = $this->cycle($participant, 2);
        $third = $this->cycle($participant, 3);
        $triggers = app(ParticipantConnectionTriggerService::class);

        $this->assertTrue($triggers->isDue($connection, $first));
        $this->assertSame(
            [$connection->getKey()],
            $triggers->dueForCycle($first)->modelKeys(),
        );

        $triggers->recordSuccess($connection, $first);

        $this->assertFalse($triggers->isDue($connection, $first));
        $this->assertFalse($triggers->isDue($connection, $second));
        $this->assertTrue($triggers->isDue($connection, $third));

        $connection->configuration = [
            'model' => 'example-model',
            'trigger' => ['mode' => 'manual'],
        ];
        $connection->save();

        $this->assertFalse($triggers->isDue($connection, $third));
    }

    public function test_connection_options_describe_supported_providers_and_triggers(): void
    {
        $this->environment();

        $this->getJson('/api/connection-options')
            ->assertOk()
            ->assertJsonPath('data.providers', ['openai', 'anthropic'])
            ->assertJsonPath('data.trigger_modes', [
                'every_cycle',
                'cycle_interval',
                'manual',
            ]);
    }

    /**
     * @return array{Substrate, Participant}
     */
    private function environment(): array
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'Connection owner',
            'email' => 'owner@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();

        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'connection-tests',
            'title' => 'Connection tests',
            'visibility' => 'public',
            'configuration' => ['maximum_participants' => 10],
        ]);
        $substrate->save();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $participantId = $this->postJson(
            "/api/substrates/{$substrate->slug}/participants",
            ['callsign' => 'connection-owner'],
        )
            ->assertCreated()
            ->json('data.id');

        return [
            $substrate,
            Participant::query()->findOrFail($participantId),
        ];
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function connection(
        Participant $participant,
        array $configuration = [],
    ): ParticipantConnection {
        $connection = new ParticipantConnection;
        $connection->forceFill([
            'participant_id' => $participant->getKey(),
            'provider' => ConnectionProvider::OpenAI,
            'name' => 'Primary connection',
            'is_enabled' => true,
            'configuration' => [
                'model' => 'example-model',
                'trigger' => ['mode' => 'every_cycle', 'interval' => 1],
                ...$configuration,
            ],
            'credentials' => ['api_key' => 'connection-secret'],
        ]);
        $connection->save();

        return $connection;
    }

    private function cycle(
        Participant $participant,
        int $sequence,
    ): Cycle {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => $sequence,
        ]);
        $cycle->save();

        return $cycle;
    }

    private function connectionsUrl(
        Substrate $substrate,
        Participant $participant,
    ): string {
        return "/api/substrates/{$substrate->slug}"
            ."/participants/{$participant->getKey()}/connections";
    }

    private function connectionUrl(
        Substrate $substrate,
        Participant $participant,
        ParticipantConnection $connection,
    ): string {
        return $this->connectionsUrl($substrate, $participant)
            ."/{$connection->getKey()}";
    }
}
