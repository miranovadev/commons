<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_exploration_states', function (Blueprint $table): void {
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('structural_position');
            $table->unsignedBigInteger('epoch')->default(0);
            $table->primary(['context_horizon_id', 'structural_position'], 'horizon_exploration_position');
        });

        // Rebuild the key portably, retaining every existing witness verbatim.
        // Epoch zero is the pre-rollout field; absent state rows also mean zero.
        Schema::create('epoch_continuation_failures', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('structural_position');
            $table->unsignedBigInteger('exploration_epoch');
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->timestampTz('failed_at');
            $table->primary(['context_horizon_id', 'structural_position', 'exploration_epoch', 'proposition_id'], 'continuation_failure_epoch');
            $table->index('proposition_id', 'continuation_failure_proposition');
        });
        DB::table('epoch_continuation_failures')->insertUsing(
            ['proposition_id', 'context_horizon_id', 'structural_position', 'exploration_epoch', 'phase_id', 'failed_at'],
            DB::table('continuation_failures as failure')
                ->join('propositions as node', 'node.id', '=', 'failure.proposition_id')
                ->join('phases as producer', 'producer.id', '=', 'node.phase_id')
                ->selectRaw('failure.proposition_id, failure.context_horizon_id, producer.kind, 0, failure.phase_id, failure.failed_at'),
        );
        Schema::drop('continuation_failures');
        Schema::rename('epoch_continuation_failures', 'continuation_failures');
    }

    public function down(): void
    {
        // The old primary key cannot represent repeated failures across epochs.
        // Refuse rollback before touching any schema or historical evidence.
        if (DB::table('continuation_failures')->exists() || DB::table('horizon_exploration_states')->exists()) {
            throw new RuntimeException('Cannot discard exploration epoch history. Restore a pre-rollout backup instead.');
        }
        Schema::drop('continuation_failures');
        Schema::drop('horizon_exploration_states');
        Schema::create('continuation_failures', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->timestampTz('failed_at');
        });
    }
};
