<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Invalidate disposable ownership projections together. A later partial
        // refresh must not certify untouched ancestry-derived terrain as current.
        app(\App\Services\RegionalExhaustionMembership::class)->rebuild();
        DB::table('substrates')->whereIn('id', DB::table('context_horizons')->select('substrate_id'))
            ->increment('field_version');
        DB::table('context_horizons')->orderBy('id')->chunk(500, function ($horizons): void {
            foreach ($horizons as $horizon) {
                DB::table('potential_dirty_horizons')->updateOrInsert(['context_horizon_id' => $horizon->id],
                    ['substrate_id' => $horizon->substrate_id, 'measurements_dirty' => true]);
            }
        });
    }

    public function down(): void
    {
        // Never restore stale derived ownership or rewrite immutable history.
    }
};
