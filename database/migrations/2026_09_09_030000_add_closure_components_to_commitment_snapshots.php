<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitment_snapshots', function (Blueprint $table): void {
            // Historical snapshots remain immutable; unknown components stay null.
            $table->double('depth')->nullable();
            $table->double('cadence')->nullable();
            $table->string('depth_direction')->nullable();
            $table->string('cadence_direction')->nullable();
            $table->string('closure_history_proposition_id', 26)->nullable();
            $table->double('tension_before')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commitment_snapshots', function (Blueprint $table): void {
            $table->dropColumn(['depth', 'cadence', 'depth_direction', 'cadence_direction', 'closure_history_proposition_id', 'tension_before']);
        });
    }
};
