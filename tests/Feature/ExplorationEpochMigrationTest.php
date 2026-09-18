<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Services\ExplorationEpochService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class ExplorationEpochMigrationTest extends TestCase
{
    use DatabaseMigrations, ExhaustionFixture;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_existing_failures_keep_their_identity_and_producing_position_in_epoch_zero(): void
    {
        $this->initializeExhaustionFixture();
        $migration = require database_path('migrations/2026_09_18_000600_scope_continuation_failures_to_exploration_epochs.php');
        $migration->down();
        $nodes = [];
        foreach ([PhaseKind::Distinguish, PhaseKind::Disperse] as $kind) {
            $node = $this->node($this->phase($this->origin, $kind), $this->origin);
            $nodes[] = $node;
            DB::table('continuation_failures')->insert(['proposition_id' => $node->id,
                'context_horizon_id' => $node->phase->cycle->context_horizon_id,
                'phase_id' => $node->phase_id, 'failed_at' => '2026-09-17 12:34:56']);
        }
        $before = DB::table('continuation_failures')->orderBy('proposition_id')->get();
        $migration->up();
        foreach ($before as $row) {
            $this->assertDatabaseHas('continuation_failures', (array) $row);
        }
        foreach ($nodes as $node) {
            $this->assertDatabaseHas('continuation_failures', ['proposition_id' => $node->id,
                'structural_position' => $node->phase->kind->value, 'exploration_epoch' => 0]);
            $this->assertTrue(app(ExplorationEpochService::class)->spentNow($node, $node->phase));
        }
        $this->assertDatabaseCount('continuation_failures', 2);
        $this->assertDatabaseCount('horizon_exploration_states', 0);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot discard exploration epoch history');
        $migration->down();
    }
}
