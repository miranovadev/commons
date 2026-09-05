<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitment_snapshots', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')
                ->primary()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignUlid('substrate_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignUlid('ledger_entry_id')
                ->unique()
                ->constrained('substrate_ledger_entries')
                ->restrictOnDelete();
            $table->foreignUlid('supersession_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('correspondence', 8, 7);
            $table->decimal('chirality', 8, 7);
            $table->decimal('closure', 8, 7);
            $table->decimal('prominence', 8, 7);
            $table->decimal('turbulence', 8, 7);
            $table->decimal('tension', 8, 7);
            $table->enum('direction_before', ['emergence', 'settlement']);
            $table->enum('direction_after', ['emergence', 'settlement']);
            $table->string('calculator_version');
            $table->timestampTz('evaluated_at');

            $table->index(['substrate_id', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_snapshots');
    }
};
