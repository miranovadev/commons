<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->ulid('id');
            $table->string('name');
            $table->string('caption')->nullable();
            $table->text('profile')->nullable();
            $table->timestamps();
        });

        Schema::create('propositions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->enum('register', [
                'coherence',
                'entropy',
                'activation',
                'salience',
                'excitation',
                'modality',
                'stimulus',
                'valence',
                'resonance',
            ]);

            $table->enum('tier', ['expressive', 'committal']);
            $table->enum('visibility', ['internal', 'external']);

            // Content
            $table->text('content');

            // Minimal metadata hook (future-proof, keep empty for now)
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            // Indexes for the Commons feed
            $table->index(['register', 'tier']);
            $table->index(['visibility']);
            $table->index(['created_at']);
        });

        Schema::create('attestations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // What proposition is being attested?
            $table->ulid('proposition_id');

            // Attestation type triad
            $table->enum('type', ['support', 'refute', 'cite']);

            // Optional content
            $table->text('content')->nullable();

            $table->timestamps();

            $table->foreign('proposition_id')
                ->references('id')
                ->on('propositions')
                ->cascadeOnDelete();

            $table->index(['proposition_id', 'type']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulidMorphs('receiptable');

            $table->enum('type', [
                'dataset',
                'storage',
                'url',
            ]);

            $table->text('content');

            // Optional structured payload (future)
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('propositions');
        Schema::dropIfExists('receipts');
    }
};
