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
                $table->decimal('shape_topology', 8, 7)->nullable();
                $table->decimal('cardinal_position', 8, 7)->nullable();
                $table->string('shape_topology_direction', 8)->nullable();
                $table->string('cardinal_position_direction', 8)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['proposition_measurements', 'commitment_snapshots'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn([
                'shape_topology', 'cardinal_position', 'shape_topology_direction', 'cardinal_position_direction',
            ]));
        }
    }
};
