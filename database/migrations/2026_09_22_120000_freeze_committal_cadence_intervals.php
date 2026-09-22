<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitment_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('cadence_interval')->nullable();
        });
        $snapshots = DB::table('commitment_snapshots as s')
            ->join('propositions as p', 'p.id', '=', 's.proposition_id')
            ->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->join('cycles as c', 'c.id', '=', 'ph.cycle_id')
            ->whereNotNull('c.governing_committal_id')
            ->select('s.proposition_id', 's.evaluated_at', 'c.id as cycle_id', 'c.governing_committal_id')->get();
        foreach ($snapshots as $snapshot) {
            $others = DB::table('cycles')->where('governing_committal_id', $snapshot->governing_committal_id)
                ->where('id', '!=', $snapshot->cycle_id)->whereNotNull('completed_at');
            // Second-precision historical timestamps cannot order ties. Origin
            // has no incoming interval; an ambiguous ordinary interval stays null.
            if ((clone $others)->where('completed_at', $snapshot->evaluated_at)->exists()) {
                continue;
            }
            $count = (clone $others)->where('completed_at', '<', $snapshot->evaluated_at)->count();
            DB::table('commitment_snapshots')->where('proposition_id', $snapshot->proposition_id)
                ->update(['cadence_interval' => $count + 1]);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE commitment_snapshots ADD CONSTRAINT cadence_interval_positive CHECK (cadence_interval IS NULL OR cadence_interval >= 1)');
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION commons_cadence_interval_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.cadence_interval IS DISTINCT FROM OLD.cadence_interval THEN
                        RAISE EXCEPTION 'A frozen Cadence interval cannot change';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER cadence_interval_immutable BEFORE UPDATE OF cadence_interval ON commitment_snapshots
                FOR EACH ROW EXECUTE FUNCTION commons_cadence_interval_immutable();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER cadence_interval_immutable BEFORE UPDATE OF cadence_interval ON commitment_snapshots
                WHEN NEW.cadence_interval IS NOT OLD.cadence_interval
                BEGIN SELECT RAISE(ABORT, 'A frozen Cadence interval cannot change'); END;
                CREATE TRIGGER cadence_interval_positive BEFORE INSERT ON commitment_snapshots
                WHEN NEW.cadence_interval < 1
                BEGIN SELECT RAISE(ABORT, 'A real Cadence interval must be positive'); END;
                SQL);
        }

        // Every existing terrain was calculated with the retired Closure object.
        // Queue the existing projection machinery once at upgrade, so a later
        // partial refresh cannot certify untouched old regions as current.
        // This is disposable-cache invalidation, never a Cadence measurement.
        DB::table('proposition_measurements')->update(['calculator_version' => null]);
        DB::table('substrates')->whereIn('id', DB::table('context_horizons')->select('substrate_id'))
            ->increment('field_version');
        DB::table('context_horizons')->orderBy('id')->chunk(500, function ($horizons): void {
            foreach ($horizons as $horizon) {
                DB::table('potential_dirty_horizons')->updateOrInsert(['context_horizon_id' => $horizon->id],
                    ['substrate_id' => $horizon->substrate_id, 'measurements_dirty' => true]);
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS cadence_interval_immutable ON commitment_snapshots; DROP FUNCTION IF EXISTS commons_cadence_interval_immutable(); ALTER TABLE commitment_snapshots DROP CONSTRAINT IF EXISTS cadence_interval_positive;');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS cadence_interval_immutable; DROP TRIGGER IF EXISTS cadence_interval_positive;');
        }
        Schema::table('commitment_snapshots', fn (Blueprint $table) => $table->dropColumn('cadence_interval'));
        // Rollback must not restore a previously invalidated disposable cache.
    }
};
