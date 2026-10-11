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
            $table->unsignedBigInteger('birth_cycle_coordinate')->nullable();
            $table->foreignUlid('temporal_predecessor_proposition_id')->nullable()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('preceding_phase_proposition_id')->nullable()->constrained('propositions')->restrictOnDelete();
            $table->unsignedBigInteger('cycle_interval')->nullable();
            $table->string('unavailable_reason', 48)->nullable();
        });
        // No backfill: historical event-born observations cannot be reconstructed.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION commons_phase_profile_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Phase Profile birth observations cannot change';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER phase_profile_immutable BEFORE UPDATE ON phase_profile_observations
                FOR EACH ROW EXECUTE FUNCTION commons_phase_profile_immutable();
                ALTER TABLE phase_profile_observations ADD CONSTRAINT phase_profile_nonnegative
                CHECK ((birth_cycle_coordinate IS NULL OR birth_cycle_coordinate >= 0)
                    AND (cycle_interval IS NULL OR cycle_interval >= 0));
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER phase_profile_immutable BEFORE UPDATE ON phase_profile_observations
                BEGIN SELECT RAISE(ABORT, 'Phase Profile birth observations cannot change'); END;
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
