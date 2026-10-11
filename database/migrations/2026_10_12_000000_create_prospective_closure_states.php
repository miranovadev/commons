<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospective_closure_states', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->primary()->constrained('propositions')->cascadeOnDelete();
            $table->string('version');
            $table->boolean('frozen')->default(false);
            $table->json('observation');
        });
        // No backfill: only new authorised events may establish this state.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION commons_prospective_closure_guard() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.proposition_id IS DISTINCT FROM OLD.proposition_id OR OLD.frozen THEN
                        RAISE EXCEPTION 'Frozen Closure and Proposition identity cannot change';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER prospective_closure_guard BEFORE UPDATE ON prospective_closure_states
                FOR EACH ROW EXECUTE FUNCTION commons_prospective_closure_guard();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER prospective_closure_guard BEFORE UPDATE ON prospective_closure_states
                WHEN OLD.frozen = 1 OR NEW.proposition_id IS NOT OLD.proposition_id
                BEGIN SELECT RAISE(ABORT, 'Frozen Closure and Proposition identity cannot change'); END;
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prospective_closure_states');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS commons_prospective_closure_guard()');
        }
    }
};
