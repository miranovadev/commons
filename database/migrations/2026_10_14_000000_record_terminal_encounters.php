<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_encounters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('proposition_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('governing_committal_id')->index()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('participant_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->json('field');
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('occurred_at');
        });
        Schema::table('tragedies', function (Blueprint $table): void {
            $table->foreignUlid('terminal_encounter_id')->nullable()->unique()->constrained('terminal_encounters')->restrictOnDelete();
        });
        Schema::table('commit_region_exhaustions', function (Blueprint $table): void {
            $table->foreignUlid('terminal_encounter_id')->nullable()->constrained('terminal_encounters')->restrictOnDelete();
            $table->foreignUlid('descendant_exhaustion_id')->nullable()->constrained('commit_region_exhaustions', 'governing_committal_id')->restrictOnDelete();
            $table->json('continuation_evaluation')->nullable();
        });
        foreach (['terminal_encounters', 'tragedies', 'commit_region_exhaustions'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("CREATE FUNCTION {$table}_immutable() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Lifecycle evidence is immutable'; END; \$\$");
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_immutable()");
            } else {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    DB::unprepared("CREATE TRIGGER {$table}_immutable_{$operation} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'Lifecycle evidence is immutable'); END");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::table('terminal_encounters')->exists()) {
            throw new RuntimeException('Cannot discard immutable terminal encounter evidence.');
        }
        foreach (['terminal_encounters', 'tragedies', 'commit_region_exhaustions'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("DROP TRIGGER {$table}_immutable ON {$table}");
                DB::unprepared("DROP FUNCTION {$table}_immutable()");
            } else {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    DB::unprepared("DROP TRIGGER {$table}_immutable_{$operation}");
                }
            }
        }
        Schema::table('commit_region_exhaustions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('terminal_encounter_id');
            $table->dropConstrainedForeignId('descendant_exhaustion_id');
            $table->dropColumn('continuation_evaluation');
        });
        Schema::table('tragedies', fn (Blueprint $table) => $table->dropConstrainedForeignId('terminal_encounter_id'));
        Schema::drop('terminal_encounters');
    }
};
