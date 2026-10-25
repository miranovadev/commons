<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_chirality_states', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')->primary()->constrained('propositions')->cascadeOnDelete();
            $table->decimal('orientation', 8, 7);
            $table->decimal('attestation_cadence', 8, 7);
            $table->decimal('chirality', 8, 7);
            $table->string('orientation_direction', 8);
            $table->string('attestation_cadence_direction', 8);
            $table->json('intervals');
            // This is an own-Attestation position, never an inherited branch cursor.
            $table->unsignedBigInteger('last_position')->nullable();
            $table->foreignUlid('last_attestation_id')->nullable()->constrained('attestations')->nullOnDelete();
            $table->unsignedBigInteger('act_count')->default(0);
        });
        // Historical event positions cannot be inferred from today's population.
        // Historical substrates remain stored; live vNext requires event-born state. No backfill.
    }

    public function down(): void
    {
        Schema::dropIfExists('local_chirality_states');
    }
};
