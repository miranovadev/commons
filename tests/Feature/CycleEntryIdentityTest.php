<?php

namespace Tests\Feature;

use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Substrate;
use App\Services\CycleEntryService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CycleEntryIdentityTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        // Historical SQLite column removal rebuilds a referenced table; it must
        // run outside a test transaction, like a real migration. Each case starts
        // from a fresh schema, including cases that deliberately reject backfill.
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
    }

    public function test_origin_has_no_entry_and_live_cycles_keep_their_explicit_entry_committal(): void
    {
        [$participant, $origin, $live] = $this->enter();
        $this->assertNull($origin->entry_committal_id);
        $this->assertSame($participant->fresh()->current_proposition_id, $live->entry_committal_id);
        $this->assertSame($live->entry_committal_id, $live->phases()->firstOrFail()->gate_proposition_id);
        $this->assertSame($live->entry_committal_id, $live->entryCommittal->id);
        $this->assertNotSame($origin->context_horizon_id, $live->context_horizon_id);

        // Completing a Cycle and moving the participant never rewrites ownership.
        $live->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $this->assertSame($live->entry_committal_id, $live->fresh()->entry_committal_id);
    }

    public function test_eloquent_cannot_clear_entry(): void
    {
        [, , $live] = $this->enter();
        $this->expectException(DomainException::class);
        $live->forceFill(['entry_committal_id' => null])->save();
    }

    public function test_bulk_updates_cannot_clear_entry(): void
    {
        [, , $live] = $this->enter();
        $this->expectException(QueryException::class);
        DB::table('cycles')->where('id', $live->id)->update(['entry_committal_id' => null]);
    }

    public function test_origin_null_cannot_be_replaced_after_creation(): void
    {
        [, $origin, $live] = $this->enter();
        $this->expectException(QueryException::class);
        DB::table('cycles')->where('id', $origin->id)->update(['entry_committal_id' => $live->entry_committal_id]);
    }

    public function test_bulk_updates_cannot_replace_an_existing_entry(): void
    {
        [, , $live] = $this->enter();
        [, , $other] = $this->enter('other');
        $this->expectException(QueryException::class);
        DB::table('cycles')->where('id', $live->id)->update(['entry_committal_id' => $other->entry_committal_id]);
    }

    public function test_rename_preserves_rows_and_restores_the_old_guard_on_rollback(): void
    {
        [, $origin, $live] = $this->enter();
        $before = DB::table('cycles')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $rename = require database_path('migrations/2026_09_22_210000_rename_cycle_governance_to_entry.php');
        $rename->down();
        $this->assertFalse(Schema::hasColumn('cycles', 'entry_committal_id'));
        $this->assertSame($live->entry_committal_id, DB::table('cycles')->where('id', $live->id)->value('governing_committal_id'));
        $this->assertNull(DB::table('cycles')->where('id', $origin->id)->value('governing_committal_id'));
        try {
            DB::transaction(fn () => DB::table('cycles')->where('id', $live->id)->update(['governing_committal_id' => null]));
            $this->fail('Rollback must restore the historical immutability guard.');
        } catch (QueryException $error) {
            $this->assertStringContainsString('cannot change its governing Committal', $error->getMessage());
        }
        $rename->up();
        $this->assertFalse(Schema::hasColumn('cycles', 'governing_committal_id'));
        $this->assertSame($before, DB::table('cycles')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertTrue(Schema::hasIndex('cycles', 'cycles_entry_completed_index'));
        $foreign = collect(Schema::getForeignKeys('cycles'))->first(fn ($key) => $key['columns'] === ['entry_committal_id']);
        $this->assertSame('propositions', $foreign['foreign_table']);
        $this->assertSame(['id'], $foreign['foreign_columns']);
        $this->assertSame('restrict', strtolower($foreign['on_delete']));
        // Idempotent writes are allowed; changing the value still fails after upgrade.
        DB::table('cycles')->where('id', $live->id)->update(['entry_committal_id' => $live->entry_committal_id]);
        $this->expectException(QueryException::class);
        DB::table('cycles')->where('id', $live->id)->update(['entry_committal_id' => null]);
    }

    public function test_entry_foreign_key_rejects_a_missing_committal(): void
    {
        [$participant, , $live] = $this->enter();
        $this->expectException(QueryException::class);
        DB::table('cycles')->insert([
            'id' => (string) str()->ulid(), 'participant_id' => $participant->id, 'sequence' => 3,
            'context_horizon_id' => $live->context_horizon_id, 'entry_committal_id' => (string) str()->ulid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_entry_must_belong_to_the_selected_horizon_and_substrate(): void
    {
        [$participant, , $live] = $this->enter();
        [, , $other] = $this->enter('other');
        $this->expectException(DomainException::class);
        (new Cycle)->forceFill([
            'participant_id' => $participant->id, 'sequence' => 3,
            'context_horizon_id' => $live->context_horizon_id,
            'entry_committal_id' => $other->entry_committal_id,
        ])->save();
    }

    public function test_migration_uses_original_entry_without_changing_snapshots_or_horizons(): void
    {
        [$participant, $origin, $live] = $this->enter();
        $participant->forceFill(['current_proposition_id' => null])->save();
        $snapshots = CommitmentSnapshot::get()->map->getAttributes()->all();
        $horizons = DB::table('context_horizons')->orderBy('id')->get()->toJson();
        $rename = require database_path('migrations/2026_09_22_210000_rename_cycle_governance_to_entry.php');
        $rename->down();
        $migration = require database_path('migrations/2026_09_22_100000_bind_cycles_to_governing_committals.php');
        $migration->down();
        $migration->up();
        $rename->up();
        $this->assertNull($origin->fresh()->entry_committal_id);
        $this->assertSame($live->entry_committal_id, $live->fresh()->entry_committal_id);
        $this->assertSame($snapshots, CommitmentSnapshot::get()->map->getAttributes()->all());
        $this->assertSame($horizons, DB::table('context_horizons')->orderBy('id')->get()->toJson());
    }

    public function test_migration_rejects_missing_entry_instead_of_guessing_from_horizon(): void
    {
        [$participant, , $live] = $this->enter();
        $rename = require database_path('migrations/2026_09_22_210000_rename_cycle_governance_to_entry.php');
        $rename->down();
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
