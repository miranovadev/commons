<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Services\HorizonReceiptService;
use App\Services\InstrumentExecutionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class HorizonProofMigrationTest extends TestCase
{
    use DatabaseMigrations, ExhaustionFixture;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        // Fixtures can contain evidence whose rollback deliberately refuses deletion.
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    private function observation(): array
    {
        $phase = $this->phase($this->origin, PhaseKind::Persist);
        $source = $this->node($phase, $this->origin);
        $service = app(InstrumentExecutionService::class);
        $receipt = $service->complete($service->start($phase, $source, 'citation', $this->origin->id), $source);

        return [$source, $receipt];
    }

    public function test_backfill_reserves_legacy_reused_evidence_without_deleting_any_historical_attachment(): void
    {
        [$source, $receipt] = $this->observation();
        $targets = [];
        foreach (range(1, 2) as $i) {
            $oldSource = $i === 1 ? $source : $this->node($source->phase, $this->origin);
            $target = $this->node($this->phase($oldSource, PhaseKind::Constrain), $oldSource);
            $targets[] = $target->id;
            DB::table('reclassification_results')->insert(['source_proposition_id' => $oldSource->id,
                'register' => $target->register, 'target_proposition_id' => $target->id]);
            DB::table('proposition_receipt_links')->insert(['receipt_id' => $receipt->id,
                'proposition_id' => $target->id, 'created_at' => now()->addSeconds($i)]);
        }
        $original = DB::table('receipts')->where('id', $receipt->id)->first();
        $migrations = collect([
            '2026_09_18_000100_scope_receipts_to_horizons.php',
            '2026_09_18_000200_record_authoritative_rest_reason.php',
            '2026_09_18_000300_record_horizon_tragedies.php',
            '2026_09_18_000400_record_proven_horizon_exhaustion.php',
            '2026_09_18_000500_index_horizon_receipt_lookup.php',
            '2026_09_18_000600_scope_continuation_failures_to_exploration_epochs.php',
            '2026_09_19_000100_localize_exploration_positions.php',
            '2026_09_19_000200_record_tragedy_final_attempts.php',
            '2026_09_21_000100_project_tragedy_receipt_basins.php',
            '2026_09_22_000000_scope_exhaustion_to_commit_regions.php',
        ])->map(fn ($file) => require database_path('migrations/'.$file));
        foreach ($migrations->reverse() as $migration) {
            $migration->down();
        }
        // Recreate the legacy marker before the additive compatibility migration.
        DB::table('context_horizons')->where('id', $source->phase->cycle->context_horizon_id)
            ->update(['exhausted_at' => '2026-09-01 00:00:00']);
        foreach ($migrations as $migration) {
            $migration->up();
        }
        $after = DB::table('receipts')->where('id', $receipt->id)->first();
        foreach (['id', 'reference', 'metadata', 'created_at', 'receiptable_id', 'instrument_execution_id'] as $field) {
            $this->assertSame($original->$field, $after->$field);
        }
        $this->assertSame($source->phase->cycle->context_horizon_id, $after->context_horizon_id);
        $this->assertSame($source->phase_id, $after->phase_id);
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id,
            'proposition_id' => $targets[0], 'legacy' => true]);
        $this->assertDatabaseCount('proposition_receipt_links', 2);
        $this->assertTrue(app(HorizonReceiptService::class)->available($source->phase)->isEmpty());
        $horizon = DB::table('context_horizons')->where('id', $after->context_horizon_id)->first();
        $this->assertNull($horizon->exhausted_at);
        $this->assertNotNull($horizon->legacy_exhausted_at);
        $this->assertDatabaseCount('horizon_exhaustions', 0);
        $this->assertDatabaseCount('horizon_tragedies', 0);
    }

    public function test_rollback_refuses_to_discard_a_new_receipt_consumption(): void
    {
        [$source, $receipt] = $this->observation();
        $phase = $this->phase($source, PhaseKind::Constrain);
        $target = $this->node($phase, $source);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($phase, $source, $target, $receipt->id));
        $migration = require database_path('migrations/2026_09_18_000100_scope_receipts_to_horizons.php');
        try {
            $migration->down();
            $this->fail('A code-only rollback must not erase exercised evidence.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Cannot discard exercised Receipt history', $exception->getMessage());
        }
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id]);
        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }
}
