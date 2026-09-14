<?php

namespace Tests\Feature;

use App\Events\RealtimeInvalidation;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateMembership;
use App\Models\User;
use App\Services\CycleEntryService;
use App\Services\ParticipationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    // Real commits are essential here: RefreshDatabase wraps tests in a transaction.
    use DatabaseMigrations;

    private array $notifications = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'log']);
        Queue::fake();
        Event::listen(RealtimeInvalidation::class, function (RealtimeInvalidation $event): void {
            $this->notifications[] = $event;
        });
    }

    public function test_origin_and_participant_notifications_wait_for_the_outer_commit(): void
    {
        [$substrate, $participant] = $this->fixture();
        DB::beginTransaction();
        app(CycleEntryService::class)->enter($participant, $this->origin());
        $this->assertSame([], $this->notifications);
        DB::commit();

        $created = collect($this->notifications)->first(fn ($event) => $event->broadcastWith()['type'] === 'proposition.created');
        $this->assertNotNull($created);
        $proposition = Proposition::firstOrFail();
        $horizonId = $proposition->contextHorizonMembership->context_horizon_id;
        $this->assertSame(['type' => 'proposition.created', 'proposition_id' => $proposition->id,
            'context_horizon_id' => $horizonId], $created->broadcastWith());
        $this->assertSame(['private-context-horizon.'.$horizonId, 'public.context-horizon.'.$horizonId],
            array_map(fn ($channel) => $channel->name, $created->broadcastOn()));
        $this->assertTrue(collect($this->notifications)->contains(fn ($event) => $event->broadcastWith() ===
            ['type' => 'participant.state_changed', 'participant_id' => $participant->id]));
        $this->assertSame('completed', $proposition->instrumentExecutions()->first()->status);
        $this->getJson("/api/substrates/{$substrate->slug}/propositions/{$proposition->id}")
            ->assertOk()->assertJsonPath('data.id', $proposition->id)
            ->assertJsonPath('data.instruments.0.status', 'completed');
    }

    public function test_rollback_discards_even_nested_notifications(): void
    {
        [, $participant] = $this->fixture();
        DB::beginTransaction();
        app(CycleEntryService::class)->enter($participant, $this->origin());
        DB::rollBack();
        $this->assertSame([], $this->notifications);
        $this->assertDatabaseCount('propositions', 0);
    }

    public function test_participation_action_notifies_observers_and_invalid_action_does_not(): void
    {
        [, $participant] = $this->fixture();
        app(CycleEntryService::class)->enter($participant, $this->origin());
        $service = app(ParticipationService::class);
        $state = $service->stateFor($participant);
        $this->notifications = [];
        $service->contribute($participant, $state['plan']['phase']['id'], [
            'action' => 'proposition.variant', 'statement' => 'A live branch.',
        ]);
        $this->assertTrue(collect($this->notifications)->contains(fn ($event) => $event->broadcastWith()['type'] === 'proposition.created'));
        $this->assertTrue(collect($this->notifications)->contains(fn ($event) => $event->broadcastWith()['type'] === 'participant.state_changed'));
        $this->notifications = [];
        try {
            $service->contribute($participant, $state['plan']['phase']['id'], ['action' => 'proposition.variant', 'statement' => 'Stale.']);
            $this->fail('A stale Phase must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
            $this->assertSame([], $this->notifications);
        }
    }

    public function test_private_channel_auth_matches_http_access_and_unknown_channels_are_denied(): void
    {
        [$substrate, $participant, $owner] = $this->fixture();
        app(CycleEntryService::class)->enter($participant, $this->origin());
        $horizon = ContextHorizon::firstOrFail();
        $outsider = User::factory()->create();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test']);
        // The test changes drivers after application boot; register the production callbacks on that driver.
        require base_path('routes/channels.php');
        $auth = fn ($name) => $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-'.$name]);
        $auth('participant.'.$participant->id)->assertUnauthorized();
        $this->actingAs($outsider);
        $auth('participant.'.$participant->id)->assertForbidden();
        $auth('context-horizon.'.$horizon->id)->assertOk(); // public Substrate
        $substrate->visibility = 'private';
        $substrate->save();
        $auth('context-horizon.'.$horizon->id)->assertForbidden();
        $auth('substrate.'.$substrate->id)->assertForbidden();
        $membership = new SubstrateMembership;
        $membership->forceFill(['substrate_id' => $substrate->id, 'user_id' => $outsider->id, 'status' => 'invited']);
        $membership->save();
        $auth('context-horizon.'.$horizon->id)->assertOk(); // HTTP view permits invitees
        $auth('participant.'.$participant->id)->assertForbidden();
        $auth('context-horizon.missing')->assertForbidden();
        $auth('participant.missing')->assertForbidden();
        $permission = \App\Models\Permission::create(['name' => 'participants.act']);
        $role = \App\Models\Role::create(['name' => 'participant', 'scope' => 'substrate']);
        $role->permissions()->attach($permission);
        $owner->roles()->attach($role, ['substrate_id' => $substrate->id]);
        $this->actingAs($owner);
        $auth('participant.'.$participant->id)->assertOk();
        $this->getJson("/api/substrates/{$substrate->slug}/participants/{$participant->id}/participate")->assertOk();
        $systemPermission = \App\Models\Permission::create(['name' => 'system.access']);
        $systemRole = \App\Models\Role::create(['name' => 'system', 'scope' => 'system']);
        $systemRole->permissions()->attach($systemPermission);
        $outsider->roles()->attach($systemRole, ['substrate_id' => null]);
        $this->actingAs($outsider);
        $auth('participant.'.$participant->id)->assertOk();
    }

    public function test_public_graph_metadata_and_detail_do_not_leak_another_substrate(): void
    {
        [$substrate, $participant] = $this->fixture();
        $this->getJson("/api/substrates/{$substrate->slug}/realtime")->assertOk()->assertJsonCount(0, 'data.context_horizon_ids');
        $this->assertDatabaseCount('context_horizons', 0);
        app(CycleEntryService::class)->enter($participant, $this->origin());
        $proposition = Proposition::firstOrFail();
        [$other] = $this->fixture('other');
        $this->getJson("/api/substrates/{$other->slug}/propositions/{$proposition->id}")->assertNotFound();
        $substrate->visibility = 'private';
        $substrate->save();
        $this->getJson("/api/substrates/{$substrate->slug}/realtime")->assertForbidden();
        $this->getJson("/api/substrates/{$substrate->slug}/propositions/{$proposition->id}")->assertForbidden();
        $created = collect($this->notifications)->first(fn ($event) => $event->broadcastWith()['type'] === 'proposition.created');
        $this->assertCount(1, $created->broadcastOn()); // visibility checked at delivery
    }

    private function fixture(string $slug = 'realtime'): array
    {
        $user = User::factory()->create();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => $slug, 'title' => 'Realtime', 'visibility' => 'public', 'configuration' => []]);
        $substrate->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'user_id' => $user->id, 'callsign' => 'observer', 'max_read_count' => 25]);
        $participant->save();

        return [$substrate, $participant, $user];
    }

    private function origin(): array
    {
        return ['statement' => 'A grounded Origin.', 'attestation' => ['direction' => 'positive'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']];
    }
}
