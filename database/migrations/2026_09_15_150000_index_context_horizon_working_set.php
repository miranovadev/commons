<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table): void {
            $table->index(['context_horizon_id', 'id'], 'cycle_horizon_working_set');
        });
        Schema::table('propositions', function (Blueprint $table): void {
            $table->index(['phase_id', 'id'], 'proposition_phase_working_set');
        });
        Schema::table('context_horizon_memberships', function (Blueprint $table): void {
            $table->index(['context_horizon_id', 'proposition_id'], 'horizon_membership_working_set');
        });
        Schema::table('receipt_context_events', function (Blueprint $table): void {
            $table->index('participant_receipt_context_id', 'receipt_event_working_set');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_context_events', function (Blueprint $table): void {
            $table->dropIndex('receipt_event_working_set');
        });
        Schema::table('propositions', function (Blueprint $table): void {
            $table->dropIndex('proposition_phase_working_set');
        });
        Schema::table('cycles', function (Blueprint $table): void {
            $table->dropIndex('cycle_horizon_working_set');
        });
        Schema::table('context_horizon_memberships', function (Blueprint $table): void {
            $table->dropIndex('horizon_membership_working_set');
        });
    }
};
