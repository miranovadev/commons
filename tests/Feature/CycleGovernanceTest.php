<?php

namespace Tests\Feature;

use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Substrate;
use App\Services\CycleEntryService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CycleGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_origin_is_ungoverned_and_live_cycles_keep_their_explicit_entry_committal(): void
    {
        [$participant, $origin, $live] = $this->enter();
        $this->assertNull($origin->governing_committal_id);
        $this->assertSame($participant->fresh()->current_proposition_id, $live->governing_committal_id);
        $this->assertSame($live->governing_committal_id, $live->phases()->firstOrFail()->gate_proposition_id);
        $this->assertNotSame($origin->context_horizon_id, $live->context_horizon_id);

        // Completing a Cycle and moving the participant never rewrites ownership.
        $live->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $this->assertSame($live->governing_committal_id, $live->fresh()->governing_committal_id);
    }

    public function test_eloquent_cannot_clear_governance(): void
    {
        [, , $live] = $this->enter();
        $this->expectException(DomainException::class);
        $live->forceFill(['governing_committal_id' => null])->save();
    }

    public function test_bulk_updates_cannot_clear_governance(): void
    {
        [, , $live] = $this->enter();
        $this->expectException(QueryException::class);
        DB::table('cycles')->where('id', $live->id)->update(['governing_committal_id' => null]);
    }

    public function test_origin_null_cannot_be_replaced_after_creation(): void
    {
        [, $origin, $live] = $this->enter();
        $this->expectException(QueryException::class);
        DB::table('cycles')->where('id', $origin->id)->update(['governing_committal_id' => $live->governing_committal_id]);
    }

    public function test_governor_must_belong_to_the_selected_horizon_and_substrate(): void
    {
        [$participant, , $live] = $this->enter();
        [, , $other] = $this->enter('other');
        $this->expectException(DomainException::class);
        (new Cycle)->forceFill([
            'participant_id' => $participant->id, 'sequence' => 3,
            'context_horizon_id' => $live->context_horizon_id,
            'governing_committal_id' => $other->governing_committal_id,
        ])->save();
    }

    public function test_migration_uses_original_entry_without_changing_snapshots_or_horizons(): void
    {
        [, $origin, $live] = $this->enter();
        $snapshots = CommitmentSnapshot::get()->map->getAttributes()->all();
        $horizons = DB::table('context_horizons')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_09_22_100000_bind_cycles_to_governing_committals.php');
        $migration->down();
        $migration->up();
        $this->assertNull($origin->fresh()->governing_committal_id);
        $this->assertSame($live->governing_committal_id, $live->fresh()->governing_committal_id);
        $this->assertSame($snapshots, CommitmentSnapshot::get()->map->getAttributes()->all());
        $this->assertSame($horizons, DB::table('context_horizons')->orderBy('id')->get()->toJson());
    }

    public function test_migration_rejects_missing_entry_instead_of_guessing_from_horizon(): void
    {
        [$participant, , $live] = $this->enter();
        $migration = require database_path('migrations/2026_09_22_100000_bind_cycles_to_governing_committals.php');
        $migration->down();
        DB::table('cycles')->insert([
            'id' => (string) str()->ulid(), 'participant_id' => $participant->id,
            'sequence' => 3, 'context_horizon_id' => $live->context_horizon_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unambiguously backfill governing Committal');
        $migration->up();
    }

    /** @return array{Participant, Cycle, Cycle} */
    private function enter(string $slug = 'governance'): array
    {
        $substrate = (new Substrate)->forceFill(['slug' => $slug, 'title' => $slug, 'visibility' => 'private', 'configuration' => []]);
        $substrate->save();
        $participant = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer']);
        $participant->save();
        app(CycleEntryService::class)->enter($participant, [
            'statement' => 'Origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/'.$slug],
        ]);
        $cycles = $participant->cycles()->orderBy('sequence')->get();

        return [$participant, $cycles[0], $cycles[1]];
    }
}
