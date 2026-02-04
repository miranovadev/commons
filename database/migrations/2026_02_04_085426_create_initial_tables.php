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
            $table->ulid('id')->primary();
            $table->string('callsign');
            $table->string('designation')->nullable();
            $table->text('dossier')->nullable();
            $table->timestamps();
        });

        Schema::create('propositions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // The creating participant
            $table->ulid('participant_id');

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
            $table->text('statement');

            // Minimal metadata hook (future-proof, keep empty for now)
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            $table->foreign('participant_id')
                ->references('id')
                ->on('participants')
                ->restrictOnDelete();

            // Indexes for the Commons feed
            $table->index(['register', 'tier']);
            $table->index(['visibility']);
            $table->index(['created_at']);
        });

        Schema::create('attestations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // The creating participant
            $table->ulid('participant_id');

            // What proposition is being attested?
            $table->ulid('proposition_id');

            // Attestation stance triad
            $table->enum('stance', ['support', 'refute', 'cite']);

            // Optional content
            $table->text('rationale')->nullable();

            $table->timestamps();

            $table->foreign('participant_id')
                ->references('id')
                ->on('participants')
                ->restrictOnDelete();

            $table->foreign('proposition_id')
                ->references('id')
                ->on('propositions')
                ->cascadeOnDelete();

            $table->index(['proposition_id', 'stance']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // The creating participant
            $table->ulid('participant_id');

            $table->ulidMorphs('receiptable');

            $table->enum('kind', [
                'dataset',
                'storage',
                'url',
            ]);

            $table->text('reference');

            // Optional structured payload (future)
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            $table->foreign('participant_id')
                ->references('id')
                ->on('participants')
                ->restrictOnDelete();

            $table->index(['kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('propositions');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('participants');
    }
};
