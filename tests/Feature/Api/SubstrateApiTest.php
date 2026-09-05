<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Substrate;
use App\Models\SubstrateMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstrateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_user_bootstraps_with_system_access(): void
    {
        $response = $this->postJson('/api/bootstrap', [
            'name' => 'Claire',
            'email' => 'claire@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', 'system-administrator')
            ->assertJsonPath('data.user.email', 'claire@example.test');

        $this->assertAuthenticated();
        $this->assertTrue(User::query()->firstOrFail()->hasSystemAccess());

        $this->postJson('/api/bootstrap', [
            'name' => 'Another',
            'email' => 'another@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertStatus(409);
    }

    public function test_system_user_can_create_a_configured_substrate(): void
    {
        $this->bootstrapSystem();

        $this->postJson('/api/substrates', [
            'slug' => 'the-commons',
            'title' => 'The Commons',
            'description' => 'A shared cognitive substrate.',
            'visibility' => 'public',
            'configuration' => [
                'maximum_participants' => 10,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.configuration.maximum_participants',
                10,
            );

        $this->assertDatabaseHas('substrates', [
            'slug' => 'the-commons',
            'visibility' => 'public',
        ]);
    }

    public function test_user_can_join_public_but_not_uninvited_private_substrate(): void
    {
        $system = $this->bootstrapSystem();
        $public = $this->substrate('public-space', 'public');
        $private = $this->substrate('private-space', 'private');

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(
            "/api/substrates/{$public->slug}/participants",
            ['callsign' => 'visitor'],
        )
            ->assertCreated()
            ->assertJsonPath('data.callsign', 'visitor');

        $this->postJson(
            "/api/substrates/{$private->slug}/participants",
            ['callsign' => 'intruder'],
        )->assertForbidden();

        $this->assertDatabaseHas('substrate_memberships', [
            'substrate_id' => $public->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
        ]);
    }

    public function test_invited_user_can_join_a_private_substrate(): void
    {
        $system = $this->bootstrapSystem();
        $substrate = $this->substrate('quiet-space', 'private');
        $user = User::factory()->create();

        $membership = new SubstrateMembership;
        $membership->forceFill([
            'substrate_id' => $substrate->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'invited',
            'invited_by_user_id' => $system->getKey(),
        ]);
        $membership->save();

        $this->actingAs($user)
            ->postJson(
                "/api/substrates/{$substrate->slug}/participants",
                ['callsign' => 'invitee'],
            )
            ->assertCreated();

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertNotNull($membership->fresh()->participant_id);
    }

    public function test_maximum_participants_is_enforced(): void
    {
        $this->bootstrapSystem();
        $substrate = $this->substrate('tiny-space', 'public', 1);

        $first = User::factory()->create();
        $this->actingAs($first)->postJson(
            "/api/substrates/{$substrate->slug}/participants",
            ['callsign' => 'first'],
        )->assertCreated();

        $second = User::factory()->create();
        $this->actingAs($second)->postJson(
            "/api/substrates/{$substrate->slug}/participants",
            ['callsign' => 'second'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('substrate');
    }

    private function bootstrapSystem(): User
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'System',
            'email' => 'system@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();

        return User::query()->firstOrFail();
    }

    private function substrate(
        string $slug,
        string $visibility,
        int $maximumParticipants = 100,
    ): Substrate {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => $slug,
            'title' => str($slug)->headline(),
            'visibility' => $visibility,
            'configuration' => [
                'maximum_participants' => $maximumParticipants,
            ],
        ]);
        $substrate->save();

        return $substrate;
    }
}
