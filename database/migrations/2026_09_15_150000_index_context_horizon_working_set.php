<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('context_horizon_memberships', function (Blueprint $table): void {
            $table->index(['context_horizon_id', 'proposition_id'], 'horizon_membership_working_set');
        });
    }

    public function down(): void
    {
        Schema::table('context_horizon_memberships', function (Blueprint $table): void {
            $table->dropIndex('horizon_membership_working_set');
        });
    }
};
