<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposition_measurements', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')
                ->primary()
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('correspondence', 8, 7)->nullable();
            $table->decimal('chirality', 8, 7)->nullable();
            $table->decimal('closure', 8, 7)->nullable();
            $table->timestampTz('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposition_measurements');
    }
};
