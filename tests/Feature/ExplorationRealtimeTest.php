<?php

namespace Tests\Feature;

use App\Events\RealtimeInvalidation;
use App\Services\ExplorationEventService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\ParticipantExplorationFixture;
use Tests\TestCase;

class ExplorationRealtimeTest extends TestCase
{
    use DatabaseMigrations, ParticipantExplorationFixture;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_personal_events_are_commit_scoped_compact_and_idempotent(): void
    {
        config(['broadcasting.default' => 'log']);
        Queue::fake();
        $events = [];
        Event::listen(RealtimeInvalidation::class, function ($event) use (&$events): void {
            $events[] = $event->broadcastWith();
        });
        [, , , $head, $terminal] = $this->personalJourney();
        $proof = app(ExplorationEventService::class)->inspect($terminal);
        $events = [];
        DB::beginTransaction();
        $this->rest($terminal);
        $this->assertSame([], $events);
        DB::rollBack();
        $this->assertSame([], $events);
        $this->assertDatabaseCount('exploration_events', 0);
        $terminal = $terminal->fresh();
        $this->rest($terminal);
        $this->assertDatabaseCount('exploration_events', 1);
        $personal = collect($events)->where('type', 'participant.state_changed');
        $this->assertTrue($personal->contains('participant_id', $terminal->cycle->participant_id));
        $this->assertFalse(collect($events)->contains(fn ($event) => in_array($event['type'], ['proposition.spent', 'exploration.epoch_advanced', 'horizon.tragedy', 'horizon.exhausted'])));
        $history = DB::table('exploration_events')->get()->toJson();
        $events = [];
        app(ExplorationEventService::class)->publish($terminal, $proof);
        $this->assertSame([], $events);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
    }
}
