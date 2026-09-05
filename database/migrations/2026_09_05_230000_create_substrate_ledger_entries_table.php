<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substrate_ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('substrate_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->enum('kind', [
                'proposition',
                'supersession',
                'attestation',
                'receipt',
            ]);
            $table->string('ledgerable_type');
            $table->ulid('ledgerable_id');
            $table->timestampTz('recorded_at');

            $table->unique(
                ['substrate_id', 'sequence'],
                'ledger_substrate_sequence_unique',
            );
            $table->unique(
                ['ledgerable_type', 'ledgerable_id'],
                'ledger_subject_unique',
            );
            $table->index(
                ['substrate_id', 'kind', 'sequence'],
                'ledger_substrate_kind_sequence_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substrate_ledger_entries');
    }
};
