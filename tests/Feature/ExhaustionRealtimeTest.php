<?php

namespace Tests\Feature;

use App\Events\GraphParticipantChanged;
use App\Events\RealtimeInvalidation;
use App\Services\ExhaustionService;
use App\Services\ExplorationEventService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\HorizonProofFixture;
use Tests\TestCase;

class ExhaustionRealtimeTest extends TestCase
{
    use DatabaseMigrations, HorizonProofFixture;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
        config(['broadcasting.default' => 'log']);
        Queue::fake();
    }

    protected function tearDown(): void
    {
        // These tests deliberately open transactions to observe after-commit
        // delivery. A failed assertion/setup must not leave their locks behind
        // for the next DatabaseMigrations fixture's schema refresh.
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_terminal_encounters_and_exhaustion_publish_minimal_events_after_commit(): void
    {
        $events = $positions = [];
        Event::listen(RealtimeInvalidation::class, function ($event) use (&$events): void {
            $events[] = $event->broadcastWith();
        });
        Event::listen(GraphParticipantChanged::class, function ($event) use (&$positions): void {
            $positions[] = $event->broadcastWith();
        });
        DB::beginTransaction();
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $this->rest($terminal);
        $this->assertSame([], $events);
        $this->assertSame([], $positions);
        DB::commit();
        $this->assertContains('horizon.tragedy', array_column($events, 'type'));
        $event = collect($events)->firstWhere('type', 'horizon.tragedy');
        $this->assertSame($opening->id, $event['governing_committal_id']);
        $this->assertSame('commit_region', $event['scope']);
        $this->assertTrue(app(ExhaustionService::class)->boundaryExhausted($horizon));
        // Immediate Exhaustion does not consume the warrant.
        $this->assertNotContains('receipt.consumed', array_column($events, 'type'));
        foreach (['horizon.exhausted', 'context-horizons.changed', 'graph.metrics_changed', 'participant.state_changed'] as $type) {
            $this->assertContains($type, array_column($events, 'type'));
        }
        $event = collect($events)->firstWhere('type', 'horizon.exhausted');
        $this->assertSame($opening->id, $event['governing_committal_id']);
        $this->assertTrue($event['opening_region_exhausted']);
        $this->assertTrue($event['boundary_region_exhausted']);
        $this->assertNotEmpty($positions);
        foreach (array_merge($events, $positions) as $event) {
            $this->assertLessThan(350, strlen(json_encode($event)));
            $this->assertArrayNotHasKey('statement', $event);
            $this->assertArrayNotHasKey('evidence', $event);
        }
    }

    public function test_rolled_back_proof_cannot_escape_into_the_realtime_stream(): void
    {
        [, $phase] = $this->exhaustedBranch();
        $proof = app(ExplorationEventService::class)->inspect($phase);
        $events = [];
        Event::listen(RealtimeInvalidation::class, function ($event) use (&$events): void {
            $events[] = $event;
        });
        DB::beginTransaction();
        $this->boundaryTragedyFixture($phase->gate);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['rested_at' => now(), 'rest_reason' => 'no_remaining_potential'])->save();
        app(ExplorationEventService::class)->publish($phase, $proof);
        $phase->cycle->forceFill(['completed_at' => now()])->save();
        $this->assertDatabaseCount('tragedies', 1);
        DB::rollBack();
        $this->assertSame([], $events);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }
}
