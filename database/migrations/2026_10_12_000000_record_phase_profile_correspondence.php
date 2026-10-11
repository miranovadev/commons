<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['proposition_measurements', 'commitment_snapshots', 'canonical_measurements'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->decimal('phase_profile', 8, 7)->nullable();
                $table->string('phase_profile_direction', 8)->nullable();
            });
        }
        // Retain historical Cardinal data under its original name, without using
        // it for new calculations or requiring it on new canonical records.
        Schema::table('canonical_measurements', function (Blueprint $table): void {
            $table->decimal('cardinal_position', 8, 7)->nullable()->change();
            $table->string('cardinal_position_direction')->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach (['proposition_measurements', 'commitment_snapshots', 'canonical_measurements'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['phase_profile', 'phase_profile_direction']));
        }
        // Do not fabricate Cardinal values for canonical records born after replacement.
    }
};
