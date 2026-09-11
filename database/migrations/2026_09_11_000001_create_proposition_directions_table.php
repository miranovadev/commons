<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposition_directions', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')->primary()->constrained()->cascadeOnDelete();
            foreach (['correspondence', 'chirality', 'closure', 'lean'] as $axis) {
                $table->string($axis)->nullable();
            }
        });

        // Preserve all recoverable pre-upgrade signs before a projection is repaired.
        // Earlier signs already overwritten by zero cannot be reconstructed here.
        DB::table('proposition_directions')->insertUsing(
            ['proposition_id', 'correspondence', 'chirality', 'closure'],
            DB::table('proposition_measurements')->select('proposition_id')->selectRaw(implode(', ', array_map(
                fn ($axis) => "CASE WHEN {$axis} > 0 THEN 'positive' WHEN {$axis} < 0 THEN 'negative' ELSE NULL END",
                ['correspondence', 'chirality', 'closure'],
            ))),
        );
        // Lean has no numeric projection column. Seed it using the same effective,
        // receipt-weighted calculation Bias uses, before new evidence can balance it.
        $chirality = app(\App\Services\Observables\ChiralityCalculator::class);
        $directions = app(\App\Services\MeasurementDirectionService::class);
        \App\Models\Proposition::query()->with('phase.cycle.participant.substrate')->chunkById(200,
            function ($positions) use ($chirality, $directions): void {
                foreach ($positions as $position) {
                    $directions->record($position, ['lean' => $chirality->leanFor(
                        collect([$position->id]), $position->phase->cycle->participant->substrate,
                    )]);
                }
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('proposition_directions');
    }
};
