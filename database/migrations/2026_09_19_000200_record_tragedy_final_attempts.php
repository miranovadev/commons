<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tragedy_final_attempts', function (Blueprint $table): void {
            $table->foreignUlid('tragedy_id')->primary()->constrained('horizon_tragedies')->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained('phases')->restrictOnDelete();
            $table->foreignUlid('proposition_id')->unique()->constrained('propositions')->restrictOnDelete();
            $table->timestampTz('started_at');
        });
    }

    public function down(): void
    {
        if (DB::table('tragedy_final_attempts')->exists()) {
            throw new RuntimeException('Cannot discard recorded Tragedy final attempts.');
        }
        Schema::dropIfExists('tragedy_final_attempts');
    }
};
