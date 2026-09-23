<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Fresh installs run the historical entry/Cadence backfills first, then this
    // rename before the ownership refresh that uses current application services.
    // Existing installations apply this pending migration to their stored IDs.
    public function up(): void
    {
        $this->rename('governing_committal_id', 'entry_committal_id', 'governor', 'entry', 'entry');
    }

    public function down(): void
    {
        $this->rename('entry_committal_id', 'governing_committal_id', 'entry', 'governor', 'governing');
    }

    private function rename(string $from, string $to, string $oldName, string $newName, string $label): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("DROP TRIGGER IF EXISTS cycles_{$oldName}_immutable ON cycles; DROP FUNCTION IF EXISTS commons_cycle_{$oldName}_immutable();");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP TRIGGER IF EXISTS cycles_{$oldName}_immutable");
        }

        // Rename in place: no inference, data backfill, cache invalidation or
        // changes to regional governance, frozen evidence or participant state.
        Schema::table('cycles', fn (Blueprint $table) => $table->renameColumn($from, $to));
        Schema::table('cycles', function (Blueprint $table) use ($oldName, $newName, $to): void {
            $table->dropIndex("cycles_{$oldName}_completed_index");
            $table->index([$to, 'completed_at'], "cycles_{$newName}_completed_index");
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE cycles RENAME CONSTRAINT cycles_{$from}_foreign TO cycles_{$to}_foreign");
            DB::unprepared(<<<SQL
                CREATE FUNCTION commons_cycle_{$newName}_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.{$to} IS DISTINCT FROM OLD.{$to} THEN
                        RAISE EXCEPTION 'A Cycle cannot change its {$label} Committal';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER cycles_{$newName}_immutable BEFORE UPDATE OF {$to} ON cycles
                FOR EACH ROW EXECUTE FUNCTION commons_cycle_{$newName}_immutable();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<SQL
                CREATE TRIGGER cycles_{$newName}_immutable BEFORE UPDATE OF {$to} ON cycles
                WHEN NEW.{$to} IS NOT OLD.{$to}
                BEGIN SELECT RAISE(ABORT, 'A Cycle cannot change its {$label} Committal'); END;
                SQL);
        }
    }
};
