<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_profile_observations', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->primary()->constrained('propositions')->cascadeOnDelete();
            // Nullable only at an unavailable historical boundary, never an unexpressed Phase.
            $table->unsignedBigInteger('developmental_cycle_position')->nullable();
            $table->unsignedBigInteger('cycle_interval')->nullable();
        });
        // Prospective only. No population walk, historical reconstruction, or backfill.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION commons_phase_profile_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.proposition_id IS DISTINCT FROM OLD.proposition_id
                        OR NEW.developmental_cycle_position IS DISTINCT FROM OLD.developmental_cycle_position
                        OR NEW.cycle_interval IS DISTINCT FROM OLD.cycle_interval THEN
                        RAISE EXCEPTION 'A Phase Profile birth observation cannot change';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER phase_profile_immutable BEFORE UPDATE ON phase_profile_observations
                FOR EACH ROW EXECUTE FUNCTION commons_phase_profile_immutable();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER phase_profile_immutable BEFORE UPDATE ON phase_profile_observations
                WHEN NEW.proposition_id IS NOT OLD.proposition_id
                    OR NEW.developmental_cycle_position IS NOT OLD.developmental_cycle_position
                    OR NEW.cycle_interval IS NOT OLD.cycle_interval
                BEGIN SELECT RAISE(ABORT, 'A Phase Profile birth observation cannot change'); END;
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_profile_observations');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS commons_phase_profile_immutable()');
        }
    }
};
