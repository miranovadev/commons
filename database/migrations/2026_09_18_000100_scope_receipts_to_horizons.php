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
            $table->foreignUlid('context_horizon_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('receipt_consumptions', function (Blueprint $table): void {
            $table->foreignUlid('receipt_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('source_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('proposition_id')->constrained()->restrictOnDelete();
            $table->boolean('legacy')->default(false);
            $table->timestampTz('consumed_at');
            $table->index(['context_horizon_id', 'proposition_id']);
        });
        // Ownership comes from the creating action, never its author or a later viewer.
        DB::table('receipts')->orderBy('id')->chunkById(500, function ($receipts): void {
            foreach ($receipts as $receipt) {
                $horizon = DB::table('instrument_executions as execution')
                    ->join('phases as phase', 'phase.id', '=', 'execution.phase_id')
                    ->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                    ->where('execution.id', $receipt->instrument_execution_id)->value('cycle.context_horizon_id');
                DB::table('receipts')->where('id', $receipt->id)->update(['context_horizon_id' => $horizon]);
                // Reserve evidence already used by a historical classification. Keep
                // all old links, including duplicates; do not invent new observations.
                $attachment = DB::table('proposition_receipt_links as link')
                    ->join('reclassification_results as result', 'result.target_proposition_id', '=', 'link.proposition_id')
                    ->join('propositions as proposition', 'proposition.id', '=', 'link.proposition_id')
                    ->where('link.receipt_id', $receipt->id)->orderBy('link.created_at')->orderBy('link.proposition_id')
                    ->first(['link.proposition_id', 'link.created_at', 'result.source_proposition_id', 'proposition.phase_id']);
                if ($horizon !== null && $attachment !== null) {
                    DB::table('receipt_consumptions')->insert([
                        'receipt_id' => $receipt->id, 'context_horizon_id' => $horizon,
                        'phase_id' => $attachment->phase_id, 'source_proposition_id' => $attachment->source_proposition_id,
                        'proposition_id' => $attachment->proposition_id, 'legacy' => true,
                        'consumed_at' => $attachment->created_at,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (DB::table('receipt_consumptions')->where('legacy', false)->exists()) {
            throw new RuntimeException('Cannot discard exercised Receipt history. Restore a pre-rollout backup instead.');
        }
        Schema::dropIfExists('receipt_consumptions');
        Schema::table('receipts', fn (Blueprint $table) => $table->dropConstrainedForeignId('context_horizon_id'));
    }
};
