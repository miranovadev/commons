<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclassification_results', function (Blueprint $table): void {
            $table->foreignUlid('source_proposition_id')->constrained('propositions')->cascadeOnDelete();
            $table->string('register', 16);
            $table->foreignUlid('target_proposition_id')->unique()->constrained('propositions')->cascadeOnDelete();
            $table->primary(['source_proposition_id', 'register']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclassification_results');
    }
};
