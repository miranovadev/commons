<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_results', function (Blueprint $table): void {
            $table->foreignUlid('source_proposition_id')->primary()->constrained('propositions')->cascadeOnDelete();
            $table->foreignUlid('target_proposition_id')->unique()->constrained('propositions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commit_results');
    }
};
