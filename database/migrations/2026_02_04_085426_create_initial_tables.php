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
            $table->string('callsign')->unique();
            $table->string('designation')->nullable();
            $table->timestamps();
        });

        Schema::create('cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->unsignedTinyInteger('current_phase')
                ->default(1)
                ->comment('Current position within the seven-phase cycle.');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'sequence']);
        });

        Schema::create('propositions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cycle_id')
                ->constrained()
                ->restrictOnDelete();
            $table->enum('register', [
                'activity',
                'frontier',
                'canon',
                'signal',
                'stance',
                'concord',
            ]);
            $table->enum('tier', ['expressive', 'committal']);
            $table->text('statement');
            $table->timestamps();

            $table->index(['register', 'tier']);
            $table->index('created_at');
        });

        Schema::create('supersessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cycle_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignUlid('source_proposition_id')
                ->constrained('propositions')
                ->restrictOnDelete();
            $table->foreignUlid('target_proposition_id')
                ->constrained('propositions')
                ->restrictOnDelete();
            $table->enum('type', [
                'revise',
                'reclassify',
                'variant',
                'reconcile',
                'commit',
            ]);
            $table->timestamps();

            $table->unique([
                'source_proposition_id',
                'target_proposition_id',
                'type',
            ]);
            $table->index(['target_proposition_id', 'type']);
        });

        Schema::create('attestations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cycle_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignUlid('proposition_id')
                ->constrained()
                ->restrictOnDelete();
            $table->enum('direction', ['support', 'refute']);
            $table->text('rationale')->nullable();
            $table->timestamps();

            $table->index(['proposition_id', 'direction']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cycle_id')
                ->constrained()
                ->restrictOnDelete();
            $table->ulidMorphs('receiptable');
            $table->enum('type', [
                'citation',
                'artefact',
                'hyperlink',
            ]);
            $table->text('reference');
            $table->string('signature')->nullable()->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('supersessions');
        Schema::dropIfExists('propositions');
        Schema::dropIfExists('cycles');
        Schema::dropIfExists('participants');
    }
};
