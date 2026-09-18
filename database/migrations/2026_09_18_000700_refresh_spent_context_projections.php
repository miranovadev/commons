<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Failed propositions now remain in the measured field. Never certify
        // an older projection that excluded them as current after deployment.
        $affected = DB::table('context_horizons')->whereIn('id', DB::table('continuation_failures')->select('context_horizon_id'));
        DB::table('substrates')->whereIn('id', (clone $affected)->select('substrate_id'))->increment('field_version');
        $affected->orderBy('id')->chunk(500, function ($horizons): void {
            foreach ($horizons as $horizon) {
                DB::table('potential_dirty_horizons')->updateOrInsert(['context_horizon_id' => $horizon->id],
                    ['substrate_id' => $horizon->substrate_id, 'measurements_dirty' => true]);
            }
        });
    }

    public function down(): void
    {
        // Derived projection invalidation is safe and must not be undone.
    }
};
