<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_chirality_states', function (Blueprint $table) {
            // At most two source/index/value references, frozen at the first own
            // prospective Attestation. Never infer or backfill historical context.
            $table->json('cadence_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('local_chirality_states', function (Blueprint $table) {
            $table->dropColumn('cadence_reference');
        });
    }
};
