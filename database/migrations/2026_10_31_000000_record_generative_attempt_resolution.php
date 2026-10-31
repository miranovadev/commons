<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_executions', function (Blueprint $table) {
            $table->timestampTz('attempt_admitted_at')->nullable();
            $table->json('attempt_resolution')->nullable();
        });
        // Historical observations are not retroactive reservations.
        DB::statement('CREATE UNIQUE INDEX evidence_attempt_cycle_unique ON evidence_executions (cycle_id) WHERE attempt_admitted_at IS NOT NULL');
        Schema::table('cycles', fn (Blueprint $table) => $table->timestampTz('deferred_at')->nullable());
    }

    public function down(): void
    {
        if (DB::table('evidence_executions')->whereNotNull('attempt_admitted_at')->exists()) {
            throw new RuntimeException('Cannot discard generative attempt history.');
        }
        DB::statement('DROP INDEX evidence_attempt_cycle_unique');
        Schema::table('evidence_executions', fn (Blueprint $table) => $table->dropColumn(['attempt_admitted_at', 'attempt_resolution']));
        Schema::table('cycles', fn (Blueprint $table) => $table->dropColumn('deferred_at'));
    }
};
