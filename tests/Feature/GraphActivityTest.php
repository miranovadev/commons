<?php

namespace Tests\Feature;

use App\Events\GraphParticipantChanged;
use App\Models\Participant;
use App\Models\Substrate;
use App\Models\User;
use App\Services\CycleEntryService;
use App\Services\ParticipantActivityService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GraphActivityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_snapshot_shows_canonical_positions_inactive_and_active_without_private_details(): void
    {
        [$substrate, $participant] = $this->fixture();
        $other = new Participant;
        $other->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'Vela', 'current_proposition_id' => $participant->current_proposition_id])->save();
        $url = "/api/substrates/{$substrate->slug}/graph-participants";
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data.participants')
            ->assertJsonPath('data.participants.0.current_proposition_id', $participant->current_proposition_id)
            ->assertJsonPath('data.participants.0.is_active', false);
        $activity = app(ParticipantActivityService::class);
        $token = $activity->begin($participant, 1800);
        $state = $this->getJson($url)->assertOk()->json('data.participants.0');
        $this->assertTrue($state['is_active']);
        $this->assertSame(['id', 'callsign', 'current_proposition_id', 'is_active', 'activity_expires_at'], array_keys($state));
        $this->assertArrayNotHasKey('token', $state);
        $activity->end($participant, $token);
        $this->getJson($url)->assertOk()->assertJsonPath('data.participants.0.is_active', false)
            ->assertJsonPath('data.participants.0.current_proposition_id', $participant->current_proposition_id);
        $substrate->forceFill(['visibility' => 'private'])->save();
        $this->getJson($url)->assertForbidden();
        $this->getJson("/api/substrates/{$substrate->slug}/graph-metrics")->assertForbidden();
    }

    public function test_killed_worker_expires_and_old_worker_cannot_clear_or_extend_a_new_wake(): void
    {
        $this->freezeTime();
        [, $participant] = $this->fixture();
        $activity = app(ParticipantActivityService::class);
        $snapshot = fn () => $activity->snapshot(collect([$participant]))['participants'][0];
        $old = $activity->begin($participant, 1800);
        $this->assertTrue($snapshot()['is_active']);
        $this->travel(301)->seconds();
        $this->assertFalse($snapshot()['is_active']);
        $this->assertSame($participant->current_proposition_id, $snapshot()['current_proposition_id']);
        $new = $activity->begin($participant, 1800);
        $expiry = $snapshot()['activity_expires_at'];
        $this->travel(10)->seconds();
        $activity->heartbeat($participant, $old);
        $activity->end($participant, $old);
        $this->assertSame($expiry, $snapshot()['activity_expires_at']);
        $this->assertTrue($snapshot()['is_active']);
        $activity->heartbeat($participant, $new);
        $this->assertGreaterThan($expiry, $snapshot()['activity_expires_at']);
        $activity->end($participant, $new);
        $this->assertFalse($snapshot()['is_active']);
        // Repeated heartbeats cannot extend beyond the job's hard deadline.
        $short = $activity->begin($participant, 60);
        $this->travel(80)->seconds();
        $activity->heartbeat($participant, $short);
        $this->travel(11)->seconds();
        $this->assertFalse($snapshot()['is_active']);
    }

    public function test_position_hints_wait_for_commit_disappear_on_rollback_and_stay_minimal(): void
    {
        [$substrate, $participant] = $this->fixture();
        config(['broadcasting.default' => 'log']);
        Queue::fake();
        $events = [];
        Event::listen(GraphParticipantChanged::class, function ($event) use (&$events): void {
            $events[] = $event;
        });
        DB::beginTransaction();
        $participant->forceFill(['current_proposition_id' => null])->save();
        $this->assertSame([], $events);
        DB::rollBack();
        $this->assertSame([], $events);
        $participant->refresh();
        DB::beginTransaction();
        $participant->forceFill(['current_proposition_id' => null])->save();
        $this->assertSame([], $events);
        DB::commit();
        $this->assertCount(1, $events);
        $this->assertSame(['type' => 'graph.participant_changed', 'participant_id' => $participant->id, 'substrate_id' => $substrate->id], $events[0]->broadcastWith());
        $this->assertLessThan(200, strlen(json_encode($events[0]->broadcastWith())));
        $this->assertCount(2, $events[0]->broadcastOn());
        $substrate->forceFill(['visibility' => 'private'])->save();
        $this->assertCount(1, $events[0]->broadcastOn());
        $this->assertInstanceOf(ShouldBroadcastNow::class, $events[0]);
    }

    public function test_metrics_and_batched_graph_reads_are_scoped_and_do_not_serialize_a_graph_in_metrics(): void
    {
        [$substrate, $participant] = $this->fixture();
        [$other, $outsider] = $this->fixture('other');
        $ownId = $participant->current_proposition_id;
        $otherId = $outsider->current_proposition_id;
        $this->getJson("/api/substrates/{$substrate->slug}/propositions?".http_build_query(['ids' => [$ownId, $otherId]]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownId);
        $this->getJson("/api/substrates/{$substrate->slug}/propositions?ids[]=bad")->assertUnprocessable();
        $metrics = $this->getJson("/api/substrates/{$substrate->slug}/graph-metrics")->assertOk()->json('data');
        $this->assertCount(1, $metrics);
        $this->assertSame($ownId, $metrics[0]['id']);
        $this->assertSame(['id', 'field', 'participation_count'], array_keys($metrics[0]));
        $this->assertArrayNotHasKey('statement', $metrics[0]);
        $this->assertArrayNotHasKey('parent_ids', $metrics[0]);
    }

    private function fixture(string $slug = 'graph-activity'): array
    {
        User::factory()->create();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => $slug, 'title' => 'Graph', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'Nova'])->save();
        app(CycleEntryService::class)->enter($participant, [
            'statement' => 'A grounded origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin/'.$slug],
        ]);

        return [$substrate, $participant->refresh()];
    }
}
