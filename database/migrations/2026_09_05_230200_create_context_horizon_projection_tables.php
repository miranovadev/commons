<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_horizons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('substrate_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->enum('domain', ['emergence', 'settlement']);
            $table->foreignUlid('opened_by_proposition_id')
                ->nullable()
                ->references('proposition_id')
                ->on('commitment_snapshots')
                ->restrictOnDelete();
            $table->foreignUlid('closed_by_proposition_id')
                ->nullable()
                ->references('proposition_id')
                ->on('commitment_snapshots')
                ->restrictOnDelete();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['substrate_id', 'sequence']);
            $table->index(['substrate_id', 'closed_at']);
        });

        Schema::create('context_horizon_memberships', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')
                ->primary()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUlid('context_horizon_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUlid('ledger_entry_id')
                ->unique()
                ->constrained('substrate_ledger_entries')
                ->restrictOnDelete();
            $table->timestampTz('projected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_horizon_memberships');
        Schema::dropIfExists('context_horizons');
    }
};
