<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null preserves archived Rest/consumption proofs without reinterpreting history.
        Schema::table('commit_region_exhaustions', fn (Blueprint $table) => $table->json('boundary_field')->nullable());
    }

    public function down(): void
    {
        if (DB::table('commit_region_exhaustions')->whereNotNull('boundary_field')->exists()) {
            throw new RuntimeException('Cannot discard immutable Charge-boundary exhaustion witnesses.');
        }
        Schema::table('commit_region_exhaustions', fn (Blueprint $table) => $table->dropColumn('boundary_field'));
    }
};
