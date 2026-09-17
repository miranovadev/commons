<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_exhaustions', function (Blueprint $table): void {
            $table->foreignUlid('context_horizon_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUlid('tragedy_id')->unique()->constrained('horizon_tragedies')->restrictOnDelete();
            $table->foreignUlid('receipt_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('opening_proposition_id')->nullable()->unique()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('phase_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('terminal_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('exhausted_at');
        });
        Schema::table('context_horizons', fn (Blueprint $table) => $table->timestampTz('legacy_exhausted_at')->nullable());
        // Old closure used a different proof. Preserve it as history, not authority
        // for the new final-attempt semantics; legacy pruning cannot gate cognition.
        DB::table('context_horizons')->whereNotNull('exhausted_at')->update([
            'legacy_exhausted_at' => DB::raw('exhausted_at'), 'exhausted_at' => null,
        ]);
        DB::table('context_horizons')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('potential_dirty_horizons')->updateOrInsert(
                    ['context_horizon_id' => $row->id], ['substrate_id' => $row->substrate_id, 'measurements_dirty' => true]);
            }
        });
        DB::table('substrates')->increment('field_version');
    }

    public function down(): void
    {
        if (DB::table('horizon_exhaustions')->exists()) {
            throw new RuntimeException('Cannot discard final-attempt exhaustion proof. Restore a pre-rollout backup instead.');
        }
        Schema::dropIfExists('horizon_exhaustions');
        DB::table('context_horizons')->whereNotNull('legacy_exhausted_at')->update(['exhausted_at' => DB::raw('legacy_exhausted_at')]);
        Schema::table('context_horizons', fn (Blueprint $table) => $table->dropColumn('legacy_exhausted_at'));
    }
};
