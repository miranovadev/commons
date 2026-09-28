<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['proposition_measurements', 'commitment_snapshots'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Nullable preserves historical snapshots; no inferred backfill.
                $table->decimal('orientation', 8, 7)->nullable();
                $table->decimal('attestation_cadence', 8, 7)->nullable();
                $table->string('orientation_direction', 8)->nullable();
                $table->string('attestation_cadence_direction', 8)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['proposition_measurements', 'commitment_snapshots'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn([
                'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction',
            ]));
        }
    }
};
