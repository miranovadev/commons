<?php

use App\Services\RegionalExhaustionBackfill;
use App\Services\RegionalExhaustionMembership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('horizon_tragedies', function (Blueprint $table): void {
            $table->dropUnique(['context_horizon_id']);
            $table->index('context_horizon_id');
            // Null means archived Horizon-scoped evidence, not a regional authority.
            $table->foreignUlid('governing_committal_id')->nullable()->unique()->constrained('propositions')->restrictOnDelete();
        }));
        // Both supported engines retarget existing Receipt/final-attempt/archive FKs on rename.
        // The pre-Horizon Origin-pruning event table is historical evidence only.
        Schema::rename('tragedies', 'legacy_origin_tragedies');
        Schema::rename('horizon_tragedies', 'tragedies');
        Schema::create('commit_region_exhaustions', function (Blueprint $table): void {
            $table->foreignUlid('governing_committal_id')->primary()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUlid('tragedy_id')->unique()->constrained('tragedies')->restrictOnDelete();
            $table->foreignUlid('receipt_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('terminal_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('exhausted_at');
        });
        Schema::create('exhausted_region_memberships', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->primary()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('governing_committal_id')->index();
            $table->foreign('governing_committal_id')->references('governing_committal_id')
                ->on('commit_region_exhaustions')->restrictOnDelete();
        });
        app(RegionalExhaustionBackfill::class)->run();
        app(RegionalExhaustionMembership::class)->rebuild();
        // Retain only timestamps not already reconstructible from immutable old proof.
        // This archive has no runtime actionability or recovery authority.
        Schema::create('legacy_horizon_exhaustion_markers', function (Blueprint $table): void {
            $table->foreignUlid('context_horizon_id')->primary()->constrained()->restrictOnDelete();
            $table->timestampTz('exhausted_at');
        });
        DB::table('legacy_horizon_exhaustion_markers')->insertUsing(['context_horizon_id', 'exhausted_at'],
            DB::table('context_horizons as horizon')->whereNotNull('horizon.exhausted_at')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('horizon_exhaustions as proof')
                    ->whereColumn('proof.context_horizon_id', 'horizon.id')
                    ->whereColumn('proof.exhausted_at', 'horizon.exhausted_at'))
                ->select(['horizon.id', 'horizon.exhausted_at']));
        Schema::table('context_horizons', fn (Blueprint $table) => $table->dropColumn('exhausted_at'));
        DB::table('context_horizons')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('potential_dirty_horizons')->insertOrIgnore(['context_horizon_id' => $row->id,
                    'substrate_id' => $row->substrate_id, 'measurements_dirty' => false]);
            }
        });
        DB::table('substrates')->increment('field_version');
    }

    public function down(): void
    {
        if (DB::table('commit_region_exhaustions')->exists()
            || DB::table('tragedies')->whereNotNull('governing_committal_id')->exists()) {
            throw new RuntimeException('Cannot discard regional proof or collapse it into Horizon authority. Restore a pre-rollout backup.');
        }
        // Restore the exact Production shape before earlier migrations can roll back.
        Schema::table('context_horizons', fn (Blueprint $table) => $table->timestampTz('exhausted_at')->nullable());
        DB::statement('UPDATE context_horizons SET exhausted_at = COALESCE(
            (SELECT exhausted_at FROM legacy_horizon_exhaustion_markers WHERE context_horizon_id = context_horizons.id),
            (SELECT exhausted_at FROM horizon_exhaustions WHERE context_horizon_id = context_horizons.id))');
        Schema::dropIfExists('legacy_horizon_exhaustion_markers');
        Schema::dropIfExists('exhausted_region_memberships');
        Schema::dropIfExists('commit_region_exhaustions');
        Schema::rename('tragedies', 'horizon_tragedies');
        Schema::rename('legacy_origin_tragedies', 'tragedies');
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('horizon_tragedies', function (Blueprint $table): void {
            $table->dropUnique(['governing_committal_id']);
            $table->dropConstrainedForeignId('governing_committal_id');
            $table->dropIndex(['context_horizon_id']);
            $table->unique('context_horizon_id');
        }));
    }
};
