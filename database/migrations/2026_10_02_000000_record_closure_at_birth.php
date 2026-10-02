<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closure_births', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->primary()->constrained('propositions')->cascadeOnDelete();
            $table->string('version');
            $table->json('observation');
        });
        Schema::table('commitment_snapshots', fn (Blueprint $table) => $table->json('closure_observation')->nullable());
        // No backfill: present-day population cannot reconstruct a past birth.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION commons_closure_birth_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.proposition_id IS DISTINCT FROM OLD.proposition_id
                        OR NEW.version IS DISTINCT FROM OLD.version
                        OR NEW.observation::text IS DISTINCT FROM OLD.observation::text THEN
                        RAISE EXCEPTION 'A Closure birth observation cannot change';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER closure_birth_immutable BEFORE UPDATE ON closure_births
                FOR EACH ROW EXECUTE FUNCTION commons_closure_birth_immutable();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER closure_birth_immutable BEFORE UPDATE ON closure_births
                WHEN NEW.observation IS NOT OLD.observation OR NEW.version IS NOT OLD.version OR NEW.proposition_id IS NOT OLD.proposition_id
                BEGIN SELECT RAISE(ABORT, 'A Closure birth observation cannot change'); END;
                SQL);
        }
    }

    public function down(): void
    {
        Schema::table('commitment_snapshots', fn (Blueprint $table) => $table->dropColumn('closure_observation'));
        Schema::dropIfExists('closure_births');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS commons_closure_birth_immutable()');
        }
    }
};
