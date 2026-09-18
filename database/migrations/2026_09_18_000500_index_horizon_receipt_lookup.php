<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL does not index the referencing side of a foreign key.
        // Scope availability and chronological iteration to the current Horizon.
        Schema::table('receipts', fn (Blueprint $table) => $table->index(
            ['context_horizon_id', 'created_at', 'id'], 'receipts_horizon_creation_lookup'));
    }

    public function down(): void
    {
        Schema::table('receipts', fn (Blueprint $table) => $table->dropIndex('receipts_horizon_creation_lookup'));
    }
};
