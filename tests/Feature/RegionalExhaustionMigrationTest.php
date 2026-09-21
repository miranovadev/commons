<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Http\Resources\PropositionResource;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ExhaustionService;
use App\Services\PotentialProjectionService;
use App\Services\TragedyEvidenceService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        $measurements = DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson();
        $migration->up();
        $this->assertTrue(app(PotentialProjectionService::class)->refresh($this->substrate->id));
        $this->assertSame($measurements, DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson());
        $this->assertDatabaseHas('commit_region_exhaustions', ['governing_committal_id' => $opening->id,
            'tragedy_id' => $tragedy->id, 'receipt_id' => $tragedy->receipt_id]);
        $this->assertEquals($historical, DB::table('horizon_exhaustions')->first());
        $this->assertSame($receipt, Receipt::findOrFail($tragedy->receipt_id)->getAttributes());
        $after = $tragedy->fresh()->getAttributes();
        unset($after['governing_committal_id']);
        $this->assertSame($original, $after);
        $this->assertTrue(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertSame($historical->exhausted_at, app(ExhaustionService::class)->openingRegionExhaustedAt($horizon));
        $this->assertFalse(Schema::hasColumn('context_horizons', 'exhausted_at'));
        $this->assertFalse(Schema::hasTable('horizon_tragedies'));
        foreach (['horizon_exhaustions', 'tragedy_final_attempts', 'tragedy_receipt_scopes', 'commit_region_exhaustions'] as $table) {
            $foreign = collect(Schema::getForeignKeys($table))->first(fn ($key) => $key['columns'] === ['tragedy_id']);
            $this->assertSame('tragedies', $foreign['foreign_table'], $table);
        }
        $this->assertDatabaseCount('legacy_horizon_exhaustion_markers', 0);
        $node = $terminal->gate->load(['contextHorizonMembership.horizon' => fn ($query) => $query->withOpeningRegionExhaustion()]);
        $resource = (new PropositionResource($node))->toArray(Request::create('/'));
        $this->assertSame($historical->exhausted_at, $resource['context_horizon']['opening_region_exhausted_at']);
        $this->assertArrayNotHasKey('exhausted_at', $resource['context_horizon']);
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
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon));
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
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertFalse(app(ExhaustionService::class)->unavailable($this->origin));
        $this->assertTrue(app(ExhaustionService::class)->unavailable($seed));
    }

    public function test_archived_exhaustion_references_remain_readable_without_claiming_current_authority(): void
    {
        [$opening, $horizon, $childTragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $childTragedy);
        $this->rest($terminal);
        $head = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $this->explore($head);
        $rest = $this->phase($head);
        $this->measure($head, 1);
        $this->rest($rest);
        $parentTragedy = Tragedy::where('governing_committal_id', $this->origin->id)->sole();
        $receipt = Receipt::findOrFail($parentTragedy->receipt_id);
        $metadata = $receipt->metadata;
        unset($metadata['governing_committal_id']); // Exact pre-regional Receipt shape.
        DB::table('receipts')->where('id', $receipt->id)->update(['metadata' => json_encode($metadata)]);
        $before = $receipt->fresh()->getAttributes();
        $migration = $this->legacy($opening->id, $horizon->id);
        DB::table('tragedy_receipt_scopes')->where('tragedy_id', $childTragedy->id)->update(['current_committal_id' => $this->origin->id]);
        $migration->up();
        $evidence = app(TragedyEvidenceService::class)->describe($receipt->fresh());
        $row = collect($evidence['exhausted_committals'])->sole();
        $this->assertSame($opening->id, $row['id']);
        $this->assertSame($opening->statement, $row['statement']);
        $this->assertTrue($row['historical_horizon_exhaustion']);
        $this->assertFalse($row['regional_exhaustion_proven']);
        $this->assertFalse($row['exhausted']);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($opening));
        $this->assertSame($before, $receipt->fresh()->getAttributes());
    }

    public function test_unproven_timestamps_and_pre_horizon_tragedies_survive_as_archives_only(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $horizon = $this->origin->phase->cycle->contextHorizon;
        $migration = require database_path('migrations/2026_09_22_000000_scope_exhaustion_to_commit_regions.php');
        $migration->down();
        $this->assertTrue(Schema::hasTable('horizon_tragedies'));
        $this->assertTrue(Schema::hasColumn('context_horizons', 'exhausted_at'));
        $timestamp = '2026-09-01 00:00:00';
        DB::table('context_horizons')->where('id', $horizon->id)->update(['exhausted_at' => $timestamp]);
        $persistedTimestamp = DB::table('context_horizons')->where('id', $horizon->id)->value('exhausted_at');
        DB::table('tragedies')->insert([
            'id' => (string) Str::ulid(), 'root_proposition_id' => $root->id,
            'substrate_id' => $this->substrate->id, 'participant_id' => $root->phase->cycle->participant_id,
            'phase_id' => $root->phase_id, 'terminal_proposition_id' => $root->id,
            'observed_field_version' => 1, 'occurred_at' => $timestamp, 'evidence' => '{"legacy":true}',
        ]);
        $history = DB::table('tragedies')->sole();
        $migration->up();
        $this->assertEquals($history, DB::table('legacy_origin_tragedies')->sole());
        $this->assertDatabaseHas('legacy_horizon_exhaustion_markers', ['context_horizon_id' => $horizon->id, 'exhausted_at' => $timestamp]);
        $this->assertFalse(Schema::hasColumn('context_horizons', 'exhausted_at'));
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertFalse(app(ExhaustionService::class)->unavailable($root));
        // Without regional events, down restores the original historical schema and bytes.
        $migration->down();
        $this->assertSame($persistedTimestamp, DB::table('context_horizons')->where('id', $horizon->id)->value('exhausted_at'));
        $this->assertEquals($history, DB::table('tragedies')->sole());
        $migration->up();
        $this->assertEquals($history, DB::table('legacy_origin_tragedies')->sole());
    }

    private function legacy(string $seed, string $horizon): object
    {
        $proof = (array) DB::table('commit_region_exhaustions')->where('governing_committal_id', $seed)->sole();
        unset($proof['governing_committal_id']);
        DB::table('horizon_exhaustions')->insert([...$proof, 'opening_proposition_id' => $seed]);
        DB::table('exhausted_region_memberships')->delete();
        DB::table('commit_region_exhaustions')->delete();
        DB::table('tragedies')->update(['governing_committal_id' => null]);
        $migration = require database_path('migrations/2026_09_22_000000_scope_exhaustion_to_commit_regions.php');
        $migration->down();

        return $migration;
    }
}
