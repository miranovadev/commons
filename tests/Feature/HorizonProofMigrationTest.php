<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HorizonProofFixture;
use Tests\TestCase;

class HorizonProofMigrationTest extends TestCase
{
    use DatabaseMigrations, HorizonProofFixture;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_receipt_backfill_reserves_historical_classification_without_rewriting_evidence(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $this->classificationEvidenceFixture($source);
        $phase = $this->phase($source, PhaseKind::Constrain);
        $input = $this->reclassificationInputFixture($phase, $source);
        $result = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase, $input);
        $receipt = Receipt::findOrFail($input['receipt_id']);
        $before = $receipt->only(['metadata', 'reference', 'signature', 'instrument_execution_id']);
        DB::table('receipt_consumptions')->update(['legacy' => true]);
        $migration = require database_path('migrations/2026_09_18_000100_scope_receipts_to_horizons.php');
        $migration->down();
        $this->assertDatabaseHas('proposition_receipt_links', ['receipt_id' => $receipt->id, 'proposition_id' => $result->id]);
        $migration->up();
        $this->assertSame($before, $receipt->fresh()->only(array_keys($before)));
        $this->assertSame($phase->cycle->context_horizon_id, $receipt->fresh()->context_horizon_id);
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id, 'legacy' => true, 'proposition_id' => $result->id]);
        $this->assertDatabaseHas('proposition_receipt_links', ['receipt_id' => $receipt->id, 'proposition_id' => $result->id]);
        $this->assertFalse(app(HorizonReceiptService::class)->available($phase)->contains('id', $receipt->id));
    }

    public function test_legacy_horizon_closure_is_archived_and_projections_invalidated_without_new_proof(): void
    {
        $horizon = $this->origin->phase->cycle->contextHorizon;
        $version = $this->substrate->fresh()->field_version;
        $migration = require database_path('migrations/2026_09_18_000400_record_proven_horizon_exhaustion.php');
        $migration->down();
        DB::table('context_horizons')->where('id', $horizon->id)->update(['exhausted_at' => '2026-09-01 00:00:00']);
        $migration->up();
        $this->assertNull($horizon->fresh()->exhausted_at);
        $this->assertNotNull($horizon->fresh()->legacy_exhausted_at);
        $this->assertDatabaseHas('potential_dirty_horizons', ['context_horizon_id' => $horizon->id]);
        $this->assertSame($version + 1, $this->substrate->fresh()->field_version);
        $this->assertDatabaseCount('horizon_exhaustions', 0);
        $this->assertSame(0, Tragedy::count());
        $this->assertDatabaseHas('propositions', ['id' => $this->origin->id]);
    }

    public function test_rollbacks_refuse_to_discard_exercised_proof(): void
    {
        [$opening, , $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $this->rest($terminal);
        foreach (['000100_scope_receipts_to_horizons', '000300_record_horizon_tragedies', '000400_record_proven_horizon_exhaustion'] as $name) {
            $migration = require database_path('migrations/2026_09_18_'.$name.'.php');
            try {
                $migration->down();
                $this->fail('Rollback discarded persistent proof: '.$name);
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('Cannot discard', $error->getMessage());
            }
        }
        $this->assertDatabaseCount('horizon_exhaustions', 1);
        $this->assertDatabaseCount('horizon_tragedies', 1);
        $this->assertDatabaseCount('receipt_consumptions', 1);
    }
}
