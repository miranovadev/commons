<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Fresh installation contract; archived experimental database upgrades are outside Stage 4. */
class RegionalExhaustionMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_empty_installation_has_one_terminal_and_regional_proof_authority(): void
    {
        $this->assertTrue(Schema::hasColumns('terminal_encounters', ['proposition_id', 'governing_committal_id',
            'phase_id', 'participant_id', 'action', 'field', 'observed_field_version', 'occurred_at']));
        $this->assertTrue(Schema::hasColumns('commit_region_exhaustions', ['governing_committal_id',
            'terminal_encounter_id', 'descendant_exhaustion_id', 'continuation_evaluation']));
        $this->assertDatabaseCount('terminal_encounters', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertGreaterThanOrEqual(4, count(Schema::getForeignKeys('terminal_encounters')));
    }

    public function test_empty_stage_four_migration_round_trip_restores_integrity(): void
    {
        $migration = require database_path('migrations/2026_10_14_000000_record_terminal_encounters.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('terminal_encounters'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('terminal_encounters'));
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame(0, DB::table('terminal_encounters')->count());
    }
}
