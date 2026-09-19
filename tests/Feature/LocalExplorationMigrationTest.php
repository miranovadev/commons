<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Supersession;
use App\Services\ExplorationEpochService;
use App\Services\ExplorationPositionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class LocalExplorationMigrationTest extends TestCase
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

    public function test_cutover_preserves_failure_rows_and_epoch_labels_then_sources_advance_independently(): void
    {
        $this->initializeExhaustionFixture();
        $first = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $later = $this->commitFrom($first);
        $second = $this->node($this->phase($later, PhaseKind::Distinguish), $later);
        $migration = require database_path('migrations/2026_09_19_000100_localize_exploration_positions.php');
        $migration->down();
        $horizon = $first->phase->cycle->context_horizon_id;
        $this->assertSame($horizon, $second->phase->cycle->context_horizon_id);
        DB::table('horizon_exploration_states')->insert(['context_horizon_id' => $horizon, 'structural_position' => 1, 'epoch' => 7]);
        foreach ([[$first, 6], [$first, 7], [$second, 7]] as [$node, $epoch]) {
            DB::table('continuation_failures')->insert(['proposition_id' => $node->id, 'context_horizon_id' => $horizon,
                'structural_position' => 1, 'exploration_epoch' => $epoch, 'phase_id' => $node->phase_id, 'failed_at' => '2026-09-18 01:02:03']);
        }
        $history = DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson();
        $migration->up();
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson());
        $this->assertDatabaseCount('horizon_exploration_states', 2);
        $epochs = app(ExplorationEpochService::class);
        foreach ([$first, $second] as $node) {
            $this->assertSame(7, $epochs->forProposition($node));
            $this->assertTrue($epochs->spentNow($node, $node->phase));
        }
        // This isolated migration test exercises counter storage, not the HTTP acceptance journey.
        DB::transaction(fn () => $epochs->advance($second->phase));
        $this->assertSame(7, $epochs->forProposition($first));
        $this->assertSame(8, $epochs->forProposition($second));
        $this->assertTrue($epochs->spentNow($first, $first->phase));
        $this->assertFalse($epochs->spentNow($second, $second->phase));
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->orderBy('exploration_epoch')->get()->toJson());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot collapse local exploration history');
        $migration->down();
    }

    public function test_ambiguous_failed_provenance_aborts_atomically_without_rewriting_evidence(): void
    {
        $this->initializeExhaustionFixture();
        $left = $this->node($this->phase($this->origin, PhaseKind::Disperse), $this->origin);
        $right = $this->node($this->phase($this->origin, PhaseKind::Disperse), $this->origin);
        $phase = $this->phase($this->origin, PhaseKind::Mediate);
        $result = $this->node($phase, $left);
        Supersession::where('target_proposition_id', $result->id)->update(['type' => 'reconcile']);
        (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $right->id,
            'target_proposition_id' => $result->id, 'type' => 'reconcile'])->save();
        $this->assertNull(app(ExplorationPositionService::class)->forProposition($result));
        $migration = require database_path('migrations/2026_09_19_000100_localize_exploration_positions.php');
        $migration->down();
        DB::table('continuation_failures')->insert(['proposition_id' => $result->id,
            'context_horizon_id' => $phase->cycle->context_horizon_id, 'structural_position' => 5,
            'exploration_epoch' => 0, 'phase_id' => $phase->id, 'failed_at' => '2026-09-18 01:02:03']);
        $before = DB::table('continuation_failures')->get()->toJson();
        try {
            $migration->up();
            $this->fail('Ambiguous failed provenance must block cutover.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambiguous provenance', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('continuation_failures')->get()->toJson());
        $this->assertFalse(Schema::hasTable('local_exploration_states'));
        $this->assertFalse(Schema::hasColumn('horizon_exploration_states', 'structural_source_id'));
    }
}
