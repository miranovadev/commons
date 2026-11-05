<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The original guard freezes scoped Phases. A first crossing at Bias
        // also needs its still-unscoped source Phase to retain the arrival Gate.
        $different = DB::getDriverName() === 'pgsql' ? 'IS DISTINCT FROM' : 'IS NOT';
        $when = <<<SQL
            (OLD.kind = 6 AND (NEW.gate_proposition_id $different OLD.gate_proposition_id
                OR NEW.kind <> OLD.kind OR NEW.cycle_id <> OLD.cycle_id)
            AND EXISTS (SELECT 1 FROM phases dest WHERE dest.cycle_id = OLD.cycle_id
                AND dest.kind = 7 AND dest.crossover_cycle_id IS NOT NULL))
            OR (OLD.kind = 7 AND OLD.crossover_cycle_id IS NOT NULL AND NEW.cycle_id <> OLD.cycle_id)
            SQL;
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION commons_freeze_bias_arrival() RETURNS trigger LANGUAGE plpgsql AS \$\$
                BEGIN
                    IF $when THEN RAISE EXCEPTION 'A recorded Bias crossover cannot change its arrival Gate'; END IF;
                    RETURN NEW;
                END;
                \$\$;
                CREATE TRIGGER freeze_bias_arrival BEFORE UPDATE OF gate_proposition_id, kind, cycle_id ON phases
                FOR EACH ROW EXECUTE FUNCTION commons_freeze_bias_arrival()");
        } else {
            DB::statement("CREATE TRIGGER freeze_bias_arrival BEFORE UPDATE OF gate_proposition_id, kind, cycle_id ON phases
                WHEN $when BEGIN SELECT RAISE(ABORT, 'A recorded Bias crossover cannot change its arrival Gate'); END");
        }
    }

    public function down(): void
    {
        if (DB::table('phases as dest')->join('phases as source', 'source.cycle_id', '=', 'dest.cycle_id')
            ->where('source.kind', 6)->where('dest.kind', 7)->whereNotNull('dest.crossover_cycle_id')
            ->where(fn ($query) => $query->whereNull('source.crossover_cycle_id')
                ->orWhereColumn('source.crossover_cycle_id', '<>', 'dest.crossover_cycle_id'))->exists()) {
            throw new RuntimeException('Recorded Bias crossovers require their immutable arrival history.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER freeze_bias_arrival ON phases');
            DB::statement('DROP FUNCTION commons_freeze_bias_arrival()');
        } else {
            DB::statement('DROP TRIGGER freeze_bias_arrival');
        }
    }
};
