<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposition_receipt_links', function (Blueprint $table): void {
            $table->foreignUlid('proposition_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['proposition_id', 'receipt_id']);
            $table->index('receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposition_receipt_links');
    }
};
