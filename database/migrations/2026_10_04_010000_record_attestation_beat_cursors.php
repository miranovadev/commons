<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            // Actual creation position relative to the existing substrate ledger.
            // NULL identifies historical entries; never infer their order today.
            $table->unsignedBigInteger('chirality_entry_after_sequence')->nullable();
            $table->index('chirality_entry_after_sequence', 'cycles_chirality_spacing');
        });
        Schema::create('proposition_attestation_cursors', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')->primary()->constrained('propositions')->cascadeOnDelete();
            $table->unsignedBigInteger('beat_sequence')->nullable();
            $table->foreignUlid('attestation_id')->nullable()->constrained('attestations')->nullOnDelete();
            $table->foreignUlid('source_supersession_id')->nullable()->constrained('supersessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposition_attestation_cursors');
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropIndex('cycles_chirality_spacing');
            $table->dropColumn('chirality_entry_after_sequence');
        });
    }
};
