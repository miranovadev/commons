<?php

namespace Tests\Feature\Api;

use App\Models\Substrate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantCollectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_own_multiple_participants_in_one_substrate(): void
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'System',
            'email' => 'system@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();

        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'plural-participants',
            'title' => 'Plural Participants',
            'visibility' => 'public',
            'configuration' => ['maximum_participants' => 10],
        ]);
        $substrate->save();

        $user = User::factory()->create();
        $this->actingAs($user);

        $firstId = $this->postJson(
            "/api/substrates/{$substrate->slug}/participants",
            ['callsign' => 'mira'],
        )->assertCreated()->json('data.id');

        $secondId = $this->postJson(
            "/api/substrates/{$substrate->slug}/participants",
            ['callsign' => 'nova'],
        )->assertCreated()->json('data.id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(2, $substrate->participants()->whereBelongsTo($user)->count());
        $this->assertSame(1, $substrate->memberships()->whereBelongsTo($user)->count());

        $this->getJson("/api/substrates/{$substrate->slug}/participants")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.owned_by_user', true)
            ->assertJsonPath('data.1.owned_by_user', true);
    }
}
