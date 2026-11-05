<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('phases', function (Blueprint $table) {
                $table->foreignUlid('crossover_cycle_id')->nullable()->constrained('cycles')->restrictOnDelete();
                $table->char('operating_cycle_id', 26)->storedAs('coalesce(crossover_cycle_id, cycle_id)');
                $table->index('operating_cycle_id');
            });
            DB::statement('ALTER TABLE phases ADD CONSTRAINT phase_crossover_position CHECK (crossover_cycle_id IS NULL OR kind IN (6, 7))');
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION commons_freeze_phase_crossover() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.crossover_cycle_id IS DISTINCT FROM OLD.crossover_cycle_id OR
                        ((NEW.gate_proposition_id IS DISTINCT FROM OLD.gate_proposition_id OR NEW.kind <> OLD.kind)
                         AND (OLD.crossover_cycle_id IS NOT NULL OR (OLD.kind = 5 AND EXISTS (
                             SELECT 1 FROM phases bias WHERE bias.cycle_id = OLD.cycle_id AND bias.kind = 6 AND bias.crossover_cycle_id IS NOT NULL
                         )))) THEN
                        RAISE EXCEPTION 'A recorded crossover cannot change its context or arrival Gates';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER freeze_phase_crossover BEFORE UPDATE OF crossover_cycle_id, gate_proposition_id, kind ON phases
                FOR EACH ROW
                EXECUTE FUNCTION commons_freeze_phase_crossover()
                SQL);
        } else {
            // Native ADD COLUMN preserves the historical triggers referencing phases.
            DB::statement('ALTER TABLE phases ADD COLUMN crossover_cycle_id varchar REFERENCES cycles(id) ON DELETE RESTRICT CHECK (crossover_cycle_id IS NULL OR kind IN (6, 7))');
            DB::statement('ALTER TABLE phases ADD COLUMN operating_cycle_id varchar GENERATED ALWAYS AS (coalesce(crossover_cycle_id, cycle_id)) VIRTUAL');
            DB::statement('CREATE INDEX phases_operating_cycle_id_index ON phases (operating_cycle_id)');
            DB::statement("CREATE TRIGGER freeze_phase_crossover BEFORE UPDATE OF crossover_cycle_id, gate_proposition_id, kind ON phases
                WHEN NEW.crossover_cycle_id IS NOT OLD.crossover_cycle_id OR
                    ((NEW.gate_proposition_id IS NOT OLD.gate_proposition_id OR NEW.kind <> OLD.kind)
                     AND (OLD.crossover_cycle_id IS NOT NULL OR (OLD.kind = 5 AND EXISTS (
                         SELECT 1 FROM phases bias WHERE bias.cycle_id = OLD.cycle_id AND bias.kind = 6 AND bias.crossover_cycle_id IS NOT NULL
                     ))))
                BEGIN SELECT RAISE(ABORT, 'A recorded crossover cannot change its context or arrival Gates'); END");
        }
    }

    public function down(): void
    {
        if (DB::table('phases')->whereNotNull('crossover_cycle_id')->exists()) {
            throw new RuntimeException('Recorded crossovers require their immutable Phase ownership for historical reads.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER freeze_phase_crossover ON phases');
            DB::statement('DROP FUNCTION commons_freeze_phase_crossover()');
            DB::statement('ALTER TABLE phases DROP CONSTRAINT phase_crossover_position');
        } else {
            DB::statement('DROP TRIGGER freeze_phase_crossover');
            DB::statement('DROP INDEX phases_operating_cycle_id_index');
            DB::statement('ALTER TABLE phases DROP COLUMN operating_cycle_id');
            DB::statement('ALTER TABLE phases DROP COLUMN crossover_cycle_id');

            return;
        }
        Schema::table('phases', function (Blueprint $table) {
            $table->dropIndex(['operating_cycle_id']);
            $table->dropColumn('operating_cycle_id');
            $table->dropConstrainedForeignId('crossover_cycle_id');
        });
    }
};
