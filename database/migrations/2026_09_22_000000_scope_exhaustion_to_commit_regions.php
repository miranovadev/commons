<?php

use App\Services\RegionalExhaustionBackfill;
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
            // Null means archived Horizon-scoped evidence, not a regional authority.
            $table->foreignUlid('governing_committal_id')->nullable()->unique()->constrained('propositions')->restrictOnDelete();
        }));
        Schema::create('commit_region_exhaustions', function (Blueprint $table): void {
            $table->foreignUlid('governing_committal_id')->primary()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('context_horizon_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUlid('tragedy_id')->unique()->constrained('horizon_tragedies')->restrictOnDelete();
            $table->foreignUlid('receipt_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('terminal_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('exhausted_at');
        });
        app(RegionalExhaustionBackfill::class)->run();
        // Immutable old horizon_exhaustions rows retain the original timestamps.
        // This field now summarizes boundary-region exhaustion, never all owned nodes.
        DB::table('context_horizons')->update(['exhausted_at' => null]);
        foreach (DB::table('commit_region_exhaustions')->get() as $proof) {
            $origin = DB::table('commitment_snapshots')->where('proposition_id', $proof->governing_committal_id)
                ->where('calculator_version', 'origin-bootstrap-v1')->exists();
            DB::table('context_horizons')->where('id', $proof->context_horizon_id)
                ->where(fn ($q) => $q->where('opened_by_proposition_id', $proof->governing_committal_id)
                    ->when($origin, fn ($root) => $root->orWhereNull('parent_context_horizon_id')))
                ->update(['exhausted_at' => $proof->exhausted_at]);
        }
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
            || DB::table('horizon_tragedies')->whereNotNull('governing_committal_id')->exists()) {
            throw new RuntimeException('Cannot discard regional proof or collapse it into Horizon authority. Restore a pre-rollout backup.');
        }
        Schema::dropIfExists('commit_region_exhaustions');
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('horizon_tragedies', function (Blueprint $table): void {
            $table->dropUnique(['governing_committal_id']);
            $table->dropConstrainedForeignId('governing_committal_id');
            $table->unique('context_horizon_id');
        }));
    }
};
