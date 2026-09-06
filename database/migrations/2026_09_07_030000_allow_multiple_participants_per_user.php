<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropUnique(['substrate_id', 'user_id']);
            $table->index(['substrate_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropIndex(['substrate_id', 'user_id']);
            $table->unique(['substrate_id', 'user_id']);
        });
    }
};
