<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_receipt_contexts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('receipt_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('proposition_id')->constrained()->restrictOnDelete();
            $table->string('evidence_signature', 64);
            $table->timestamp('loaded_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->unique(['participant_id', 'receipt_id'], 'receipt_context_participant_receipt_unique');
            $table->index(['proposition_id', 'cleared_at']);
        });
        Schema::create('receipt_context_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_receipt_context_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->enum('action', ['loaded', 'cleared']);
            $table->timestamp('created_at');
        });
        $this->ledgerKinds(true);
        // Preserve historical allocations and snapshots. Verification is not a Read.
        DB::table('proposition_measurements')->update(['calculator_version' => null]);
    }

    public function down(): void
    {
        if (DB::table('receipt_context_events')->exists()) {
            throw new RuntimeException('Cannot discard persistent Receipt context history during rollback.');
        }
        $this->ledgerKinds(false);
        Schema::dropIfExists('receipt_context_events');
        Schema::dropIfExists('participant_receipt_contexts');
        DB::table('proposition_measurements')->update(['calculator_version' => null]);
    }

    private function ledgerKinds(bool $withContext): void
    {
        $kinds = ['proposition', 'supersession', 'attestation', 'receipt', ...($withContext ? ['receipt_context'] : [])];
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE substrate_ledger_entries DROP CONSTRAINT substrate_ledger_entries_kind_check');
            DB::statement("ALTER TABLE substrate_ledger_entries ADD CONSTRAINT substrate_ledger_entries_kind_check CHECK (kind IN ('".implode("','", $kinds)."'))");
        } else {
            Schema::table('substrate_ledger_entries', fn (Blueprint $table) => $table->enum('kind', $kinds)->change());
        }
    }
};
