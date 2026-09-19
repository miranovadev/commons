<?php

namespace Tests\Feature;

use App\Events\RealtimeInvalidation;
use App\Services\ContributionDispatcher;
use App\Services\HorizonFailureService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\SpentContinuationFixture;
use Tests\TestCase;

class SpentContinuationRealtimeTest extends TestCase
{
    use DatabaseMigrations, SpentContinuationFixture;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_spent_and_epoch_events_are_compact_commit_scoped_and_idempotent(): void
    {
        config(['broadcasting.default' => 'log']);
        Queue::fake();
        $events = [];
        Event::listen(RealtimeInvalidation::class, function ($event) use (&$events): void {
            $events[] = $event->broadcastWith();
        });
        $this->initializeExhaustionFixture();
        [$signal, $activity, $terminal, $return] = $this->testedLineage();
        $proof = app(HorizonFailureService::class)->inspect($terminal);
        $events = [];
        DB::beginTransaction();
        $this->rest($terminal);
        $this->assertSame([], $events);
        DB::rollBack();
        $this->assertSame([], $events);
        $this->assertDatabaseCount('continuation_failures', 0);

        // Real committed publication emits one ID-only hint for each new row.
        $terminal->refresh();
        $terminal->unsetRelation('cycle');
        $this->rest($terminal);
        $spent = collect($events)->where('type', 'proposition.spent');
        foreach ([$signal, $activity] as $node) {
            $this->assertContains(['type' => 'proposition.spent', 'proposition_id' => $node->id,
                'context_horizon_id' => $terminal->cycle->context_horizon_id], $spent->all());
        }
        $this->assertSame(DB::table('continuation_failures')->count(), $spent->count());
        $this->assertFalse(collect($events)->contains(fn ($event) => in_array($event['type'], ['horizon.tragedy', 'horizon.exhausted', 'context-horizons.changed'])));
        $events = [];
        app(HorizonFailureService::class)->publish($terminal, $proof);
        $this->assertSame([], $events);

        $this->measure($activity, 0);
        $events = [];
        DB::beginTransaction();
        app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
            ['action' => 'proposition.variant', 'statement' => 'Change just this position']);
        $this->assertSame([], $events);
        DB::commit();
        $advanced = collect($events)->where('type', 'exploration.epoch_advanced')->values()->all();
        $this->assertSame([['type' => 'exploration.epoch_advanced', 'structural_position' => 1,
            'structural_source_id' => $return->gate_proposition_id,
            'exploration_epoch' => 1, 'context_horizon_id' => $return->cycle->context_horizon_id]], $advanced);
    }
}
