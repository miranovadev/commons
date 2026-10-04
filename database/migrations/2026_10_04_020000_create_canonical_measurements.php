<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canonical_measurements', function (Blueprint $table) {
            $table->foreignUuid('proposition_id')->primary()->constrained('propositions')->cascadeOnDelete();
            $table->foreignUuid('source_proposition_id')->constrained('propositions');
            foreach (['correspondence', 'chirality', 'orientation', 'attestation_cadence', 'shape_topology', 'cardinal_position'] as $name) {
                $table->decimal($name, 8, 7);
                $table->string($name.'_direction');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_measurements');
    }
};
