<?php

namespace Tests\Feature;

use App\Events\GraphParticipantChanged;
use App\Events\RealtimeInvalidation;
use App\Models\Tragedy;
use App\Services\ExhaustionService;
use App\Services\PotentialProjectionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class ExhaustionRealtimeTest extends TestCase
{
    use DatabaseMigrations, ExhaustionFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
        config(['broadcasting.default' => 'log']);
        Queue::fake();
    }

    public function test_exhaustion_and_recovery_publish_only_identifier_events_after_commit(): void
    {
        [$root, $phase, , $commit] = $this->exhaustedBranch();
        $events = [];
        Event::listen(RealtimeInvalidation::class, function ($event) use (&$events): void {
            $events[] = $event->broadcastWith();
        });
        $positions = [];
        Event::listen(GraphParticipantChanged::class, function ($event) use (&$positions): void {
            $positions[] = $event->broadcastWith();
        });
        DB::beginTransaction();
        $this->rest($phase);
        $this->assertSame([], $events);
        $this->assertSame([], $positions);
        DB::commit();
        $this->assertSame(1, Tragedy::count());
        $this->assertContains($root->id, array_column($events, 'proposition_id'));
        $this->assertContains($commit->id, array_column($events, 'proposition_id'));
        $this->assertContains('context-horizons.changed', array_column($events, 'type'));
        $this->assertContains('graph.metrics_changed', array_column($events, 'type'));
        $this->assertContains('participant.state_changed', array_column($events, 'type'));
        $this->assertContains($phase->cycle->participant_id, array_column($positions, 'participant_id'));
        foreach (array_merge($events, $positions) as $event) {
            $this->assertLessThan(300, strlen(json_encode($event)));
            $this->assertArrayNotHasKey('statement', $event);
            $this->assertArrayNotHasKey('evidence', $event);
        }
    }

    public function test_rolled_back_exhaustion_cannot_escape_into_the_realtime_stream(): void
    {
        [, $phase] = $this->exhaustedBranch();
        $proof = app(ExhaustionService::class)->inspect($phase);
        $events = [];
        Event::listen(RealtimeInvalidation::class, function ($event) use (&$events): void {
            $events[] = $event;
        });
        DB::beginTransaction();
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        app(ExhaustionService::class)->publish($phase, $proof);
        app(PotentialProjectionService::class)->refresh($this->substrate->id);
        DB::rollBack();
        $this->assertSame([], $events);
        $this->assertSame(0, DB::table('exhaustion_states')->count());
        $this->assertSame(0, Tragedy::count());
    }
}
