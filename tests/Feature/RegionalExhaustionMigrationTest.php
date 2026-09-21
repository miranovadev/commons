<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ExhaustionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HorizonProofFixture;
use Tests\TestCase;

class RegionalExhaustionMigrationTest extends TestCase
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
    }

    public function test_proven_same_region_legacy_boundary_maps_without_rewriting_evidence(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $this->rest($terminal);
        $receipt = Receipt::findOrFail($tragedy->receipt_id)->getAttributes();
        $original = $tragedy->getAttributes();
        unset($original['governing_committal_id']);
        $migration = $this->legacy($opening->id, $horizon->id);
        $historical = DB::table('horizon_exhaustions')->first();
        $migration->up();
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $opening->id,
            'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id]);
        $this->assertEquals($historical, DB::table('horizon_exhaustions')->first());
        $this->assertSame($receipt, Receipt::findOrFail($tragedy->receipt_id)->getAttributes());
        $after = $tragedy->fresh()->getAttributes();
        unset($after['governing_committal_id']);
        $this->assertSame($original, $after);
        $this->assertTrue(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertNotNull($horizon->fresh()->exhausted_at);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($this->origin));
    }

    public function test_ambiguous_transported_legacy_proof_remains_history_without_regional_authority(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $this->rest($terminal);
        $migration = $this->legacy($opening->id, $horizon->id);
        DB::table('tragedy_receipt_scopes')->where('tragedy_id', $tragedy->id)->update(['current_committal_id' => $this->origin->id]);
        $historical = DB::table('horizon_exhaustions')->first();
        $consumption = DB::table('receipt_consumptions')->where('receipt_id', $tragedy->receipt_id)->first();
        $migration->up();
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertNull($tragedy->fresh()->governing_committal_id);
        $this->assertNull($horizon->fresh()->exhausted_at);
        $this->assertEquals($historical, DB::table('horizon_exhaustions')->first());
        $this->assertEquals($consumption, DB::table('receipt_consumptions')->where('receipt_id', $tragedy->receipt_id)->first());
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertFalse(app(ExhaustionService::class)->unavailable($opening));
    }

    public function test_legacy_local_proof_does_not_exhaust_its_horizons_opening_region(): void
    {
        [, $first, , $seed] = $this->exhaustedBranch();
        $this->rest($first);
        $tragedy = Tragedy::sole();
        [$terminal] = $this->exercise($seed, $tragedy);
        $this->rest($terminal);
        $horizon = $terminal->cycle->contextHorizon;
        $migration = $this->legacy($seed->id, $horizon->id);
        // Old Horizon authority was broader than the proven terminal's region.
        DB::table('horizon_exhaustions')->where('context_horizon_id', $horizon->id)
            ->update(['opening_proposition_id' => $horizon->opened_by_proposition_id]);
        DB::table('context_horizons')->where('id', $horizon->id)->update(['exhausted_at' => now()]);
        $migration->up();
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $seed->id]);
        $this->assertNull($horizon->fresh()->exhausted_at);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($this->origin));
        $this->assertTrue(app(ExhaustionService::class)->unavailable($seed));
    }

    private function legacy(string $seed, string $horizon): object
    {
        $proof = (array) DB::table('commit_region_exhaustions')->where('governing_committal_id', $seed)->sole();
        unset($proof['governing_committal_id']);
        DB::table('horizon_exhaustions')->insert([...$proof, 'opening_proposition_id' => $seed]);
        DB::table('commit_region_exhaustions')->delete();
        DB::table('horizon_tragedies')->update(['governing_committal_id' => null]);
        $migration = require database_path('migrations/2026_09_22_000000_scope_exhaustion_to_commit_regions.php');
        $migration->down();

        return $migration;
    }
}
