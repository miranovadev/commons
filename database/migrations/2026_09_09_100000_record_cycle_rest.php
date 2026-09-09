<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table): void {
            $table->timestamp('rested_at')->nullable();
        });
        Schema::table('phases', function (Blueprint $table): void {
            $table->dropUnique(['cycle_id', 'kind']);
            $table->index(['cycle_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table): void {
            $table->dropColumn('rested_at');
        });
        Schema::table('phases', function (Blueprint $table): void {
            $table->dropIndex(['cycle_id', 'kind']);
            $table->unique(['cycle_id', 'kind']);
        });
    }
};
