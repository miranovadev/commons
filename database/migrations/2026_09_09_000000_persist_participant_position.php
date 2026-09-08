<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->foreignUlid('current_proposition_id')->nullable()
                ->constrained('propositions')->restrictOnDelete();
        });

        // Recover existing positions without performing a new entry selection.
        DB::table('participants')->orderBy('id')->each(function (object $participant): void {
            $phase = DB::table('phases')
                ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')
                ->where('cycles.participant_id', $participant->id)
                ->orderByDesc('cycles.sequence')->orderByDesc('phases.kind')
                ->select('phases.*')->first();
            if ($phase === null) {
                return;
            }
            // A completed Order phase wrote the final Committal position.
            $positionId = $phase->completed_at === null ? null : DB::table('propositions')
                ->where('phase_id', $phase->id)->where('tier', 'committal')
                ->orderByDesc('id')->value('id');
            $positionId ??= $phase->gate_proposition_id;
            DB::table('participants')->where('id', $participant->id)
                ->update(['current_proposition_id' => $positionId]);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_proposition_id');
        });
    }
};
