<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->ulid('instrument_execution_id')->nullable()->change();
            $table->foreignUlid('phase_id')->nullable()->constrained()->restrictOnDelete();
        });
        DB::statement('UPDATE receipts SET phase_id = (SELECT phase_id FROM instrument_executions WHERE instrument_executions.id = receipts.instrument_execution_id)');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE receipts DROP CONSTRAINT IF EXISTS receipts_type_check');
            DB::statement("ALTER TABLE receipts ADD CONSTRAINT receipts_type_check CHECK (type IN ('citation','artefact','hyperlink','web_search','tragedy'))");
        } else {
            Schema::table('receipts', fn (Blueprint $table) => $table->enum('type', ['citation', 'artefact', 'hyperlink', 'web_search', 'tragedy'])->change());
        }
        Schema::create('horizon_tragedies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('context_horizon_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('receipt_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('participant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('terminal_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('opening_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('occurred_at');
        });
        Schema::create('horizon_failure_witnesses', function (Blueprint $table): void {
            $table->foreignUlid('phase_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('recorded_at');
        });
        Schema::create('continuation_failures', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->timestampTz('failed_at');
        });
        Schema::table('cycles', fn (Blueprint $table) => $table->foreignUlid('recovery_proposition_id')->nullable()->constrained('propositions')->restrictOnDelete());
        // SQLite rebuilds the table when adding the foreign key. Restore its
        // existing causal immutability triggers; PostgreSQL retains its triggers.
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS cycles_context_horizon_immutable BEFORE UPDATE OF context_horizon_id ON cycles
                WHEN NEW.context_horizon_id IS NOT OLD.context_horizon_id
                BEGIN SELECT RAISE(ABORT, 'Cycle Context Horizon cannot be changed'); END;");
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS cycles_causal_owner_immutable BEFORE UPDATE OF participant_id ON cycles
                WHEN NEW.participant_id IS NOT OLD.participant_id
                BEGIN SELECT RAISE(ABORT, 'Creating causal ownership cannot be changed'); END;");
        }

    }

    public function down(): void
    {
        if (DB::table('horizon_tragedies')->exists()) {
            throw new RuntimeException('Cannot discard Tragedy proof history. Restore a pre-rollout backup instead.');
        }
        Schema::dropIfExists('horizon_failure_witnesses');
        Schema::dropIfExists('continuation_failures');
        Schema::dropIfExists('horizon_tragedies');
        Schema::table('cycles', fn (Blueprint $table) => $table->dropConstrainedForeignId('recovery_proposition_id'));
        Schema::table('receipts', fn (Blueprint $table) => $table->dropConstrainedForeignId('phase_id'));
    }
};
