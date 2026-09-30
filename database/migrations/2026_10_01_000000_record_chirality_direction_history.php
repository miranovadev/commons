<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['proposition_measurements', 'commitment_snapshots'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('chirality_direction', 8)->nullable());
        }
        Schema::create('chirality_observations', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')->primary()->constrained()->cascadeOnDelete();
            $table->decimal('chirality', 8, 7);
            $table->string('chirality_direction', 8);
            $table->decimal('orientation', 8, 7)->nullable();
            $table->decimal('attestation_cadence', 8, 7)->nullable();
            $table->string('orientation_direction', 8)->nullable();
            $table->string('attestation_cadence_direction', 8)->nullable();
        });
        Schema::create('chirality_population_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('substrate_id')->constrained()->cascadeOnDelete();
            $table->string('population_key', 64)->index();
            $table->decimal('mean', 8, 7);
            $table->string('direction', 8);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chirality_population_observations');
        Schema::dropIfExists('chirality_observations');
        foreach (['proposition_measurements', 'commitment_snapshots'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('chirality_direction'));
        }
    }
};
