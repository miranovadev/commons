<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhaustion_states', function (Blueprint $table): void {
            // Excursion = expressive path; basin = a Commit's outgoing possibilities.
            // No proposition or immutable ledger row is rewritten.
            $table->foreignUlid('proposition_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUlid('substrate_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('terminal_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('exhausted_at');
            $table->json('evidence');
            $table->index(['context_horizon_id', 'kind']);
        });
        Schema::table('context_horizons', function (Blueprint $table): void {
            $table->timestampTz('exhausted_at')->nullable();
        });
        Schema::table('origin_branches', function (Blueprint $table): void {
            $table->timestampTz('exhausted_at')->nullable();
            $table->timestampTz('legacy_retired_at')->nullable();
        });
        // Preserve old decisions as legacy holds, NOT as newly proven exhaustion.
        DB::table('origin_branches')->whereNotNull('pruned_at')
            ->update(['legacy_retired_at' => DB::raw('pruned_at')]);
        // Persist dirty work so the normal projection sweep refreshes eligibility.
        DB::table('context_horizons')->orderBy('id')->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('potential_dirty_horizons')->insertOrIgnore([
                    'substrate_id' => $row->substrate_id, 'context_horizon_id' => $row->id, 'measurements_dirty' => false,
                ]);
            }
        });
        DB::table('substrates')->increment('field_version');
    }

    public function down(): void
    {
        Schema::dropIfExists('exhaustion_states');
        Schema::table('context_horizons', fn (Blueprint $table) => $table->dropColumn('exhausted_at'));
        Schema::table('origin_branches', fn (Blueprint $table) => $table->dropColumn(['exhausted_at', 'legacy_retired_at']));
    }
};
