<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exploration_events', function (Blueprint $table): void {
            $table->foreignUlid('phase_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUlid('proposition_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUlid('arrival_phase_id')->nullable()->constrained('phases')->restrictOnDelete();
            $table->json('witness');
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('recorded_at');
        });
        $valid = "SELECT 1 FROM phases rest JOIN cycles cycle ON cycle.id = rest.cycle_id
            JOIN phases arrival ON arrival.id = NEW.arrival_phase_id AND arrival.cycle_id = cycle.id
            JOIN propositions head ON head.id = NEW.proposition_id
            JOIN phases producer ON producer.id = head.phase_id
            WHERE rest.id = NEW.phase_id AND rest.gate_proposition_id = head.id
              AND rest.completed_at IS NOT NULL AND cycle.rested_at IS NOT NULL
              AND arrival.completed_at IS NOT NULL AND arrival.kind + 1 = rest.kind
              AND producer.kind + 1 = rest.kind AND head.tier = 'expressive'";
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION exploration_events_encounter() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN
                IF NOT EXISTS ({$valid}) THEN RAISE EXCEPTION 'Exploration requires a completed personal Rest arrival'; END IF;
                RETURN NEW; END; \$\$");
            DB::unprepared('CREATE TRIGGER exploration_events_encounter BEFORE INSERT ON exploration_events FOR EACH ROW EXECUTE FUNCTION exploration_events_encounter()');
        } else {
            DB::unprepared("CREATE TRIGGER exploration_events_encounter BEFORE INSERT ON exploration_events WHEN NOT EXISTS ({$valid})
                BEGIN SELECT RAISE(ABORT, 'Exploration requires a completed personal Rest arrival'); END");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION exploration_events_immutable() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Exploration history is immutable'; END; \$\$");
            DB::unprepared('CREATE TRIGGER exploration_events_immutable BEFORE UPDATE OR DELETE ON exploration_events FOR EACH ROW EXECUTE FUNCTION exploration_events_immutable()');
        } else {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER exploration_events_immutable_{$operation} BEFORE {$operation} ON exploration_events BEGIN SELECT RAISE(ABORT, 'Exploration history is immutable'); END");
            }
        }
    }

    public function down(): void
    {
        if (DB::table('exploration_events')->exists()) {
            throw new RuntimeException('Cannot discard immutable Exploration Events.');
        }
        Schema::drop('exploration_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION exploration_events_immutable()');
            DB::unprepared('DROP FUNCTION exploration_events_encounter()');
        }
    }
};
