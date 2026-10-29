<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX evidence_preparation_cycle_unique');
        Schema::table('evidence_executions', fn (Blueprint $table) => $table->index(['proposition_id', 'cycle_id', 'phase_id'], 'evidence_personal_gate_lookup'));
    }

    public function down(): void
    {
        if (DB::table('evidence_executions')->where('type', 'prepare')->select('cycle_id')->groupBy('cycle_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore Cycle uniqueness without losing acquisition history.');
        }
        DB::statement("CREATE UNIQUE INDEX evidence_preparation_cycle_unique ON evidence_executions (cycle_id) WHERE type = 'prepare'");
        Schema::table('evidence_executions', fn (Blueprint $table) => $table->dropIndex('evidence_personal_gate_lookup'));
    }
};
