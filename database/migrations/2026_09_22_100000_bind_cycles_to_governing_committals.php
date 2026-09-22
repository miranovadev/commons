<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table): void {
            $table->foreignUlid('governing_committal_id')->nullable()
                ->constrained('propositions')->restrictOnDelete();
            $table->index(['governing_committal_id', 'completed_at'], 'cycles_governor_completed_index');
        });

        // Entry is durable evidence. Never infer ownership from the current
        // participant position, a newer sibling, or the latest Horizon.
        foreach (DB::table('cycles')->orderBy('id')->cursor() as $cycle) {
            $entry = DB::table('phases')->where('cycle_id', $cycle->id)
                ->orderBy('kind')->orderBy('id')->first();
            $origin = DB::table('commitment_snapshots as snapshot')
                ->join('propositions as proposition', 'proposition.id', '=', 'snapshot.proposition_id')
                ->join('phases as phase', 'phase.id', '=', 'proposition.phase_id')
                ->join('substrate_ledger_entries as ledger', 'ledger.id', '=', 'snapshot.ledger_entry_id')
                ->where('phase.cycle_id', $cycle->id)->where('ledger.sequence', 1)
                ->where('snapshot.calculator_version', 'origin-bootstrap-v1')
                ->whereNull('snapshot.supersession_id')->exists();
            if ($origin && $entry !== null && $entry->kind === 7 && $entry->gate_proposition_id === null) {
                continue;
            }
            $governor = $entry === null ? null : DB::table('propositions as proposition')
                ->join('commitment_snapshots as snapshot', 'snapshot.proposition_id', '=', 'proposition.id')
                ->join('phases as phase', 'phase.id', '=', 'proposition.phase_id')
                ->join('cycles as owner', 'owner.id', '=', 'phase.cycle_id')
                ->where('proposition.id', $entry->gate_proposition_id)->where('proposition.tier', 'committal')
                ->select('proposition.id', 'snapshot.substrate_id', 'owner.context_horizon_id')->first();
            $horizon = DB::table('context_horizons')->where('id', $cycle->context_horizon_id)->first();
            $substrateId = DB::table('participants')->where('id', $cycle->participant_id)->value('substrate_id');
            if ($entry === null || $entry->kind !== 1 || $governor === null || $horizon === null
                || $governor->substrate_id !== $substrateId || $horizon->substrate_id !== $substrateId
                || ($governor->context_horizon_id !== $horizon->id
                    && ($horizon->opened_by_proposition_id !== $governor->id
                        || $horizon->parent_context_horizon_id !== $governor->context_horizon_id))) {
                throw new RuntimeException("Cannot unambiguously backfill governing Committal for Cycle {$cycle->id}; inspect its original entry Phase.");
            }
            DB::table('cycles')->where('id', $cycle->id)->update(['governing_committal_id' => $governor->id]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION commons_cycle_governor_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.governing_committal_id IS DISTINCT FROM OLD.governing_committal_id THEN
                        RAISE EXCEPTION 'A Cycle cannot change its governing Committal';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER cycles_governor_immutable BEFORE UPDATE OF governing_committal_id ON cycles
                FOR EACH ROW EXECUTE FUNCTION commons_cycle_governor_immutable();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER cycles_governor_immutable BEFORE UPDATE OF governing_committal_id ON cycles
                WHEN NEW.governing_committal_id IS NOT OLD.governing_committal_id
                BEGIN SELECT RAISE(ABORT, 'A Cycle cannot change its governing Committal'); END;
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS cycles_governor_immutable ON cycles; DROP FUNCTION IF EXISTS commons_cycle_governor_immutable();');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS cycles_governor_immutable');
        }
        Schema::table('cycles', function (Blueprint $table): void {
            $table->dropIndex('cycles_governor_completed_index');
            $table->dropConstrainedForeignId('governing_committal_id');
        });
    }
};
