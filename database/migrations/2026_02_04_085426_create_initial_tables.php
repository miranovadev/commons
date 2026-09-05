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
            $table->unsignedSmallInteger('max_read_count')->default(25);
            $table->unsignedInteger('max_token_count')->nullable();
            $table->timestamps();
        });

        Schema::create('cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'sequence']);
        });

        Schema::create('phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cycle_id')
                ->constrained()
                ->restrictOnDelete();
            $table->ulid('gate_proposition_id')->nullable()->index();
            $table->unsignedTinyInteger('kind')
                ->comment('Kind and position within the seven-phase cycle.');
            $table->enum('write_register', [
                'activity',
                'frontier',
                'canon',
                'signal',
                'stance',
                'concord',
            ])->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['cycle_id', 'kind']);
            $table->index(['cycle_id', 'completed_at']);
        });

        Schema::create('propositions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('phase_id')
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

        Schema::table('phases', function (Blueprint $table) {
            $table->foreign('gate_proposition_id')
                ->references('id')
                ->on('propositions')
                ->restrictOnDelete();
        });

        Schema::create('supersessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('phase_id')
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
            $table->foreignUlid('phase_id')
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

        Schema::create('instrument_executions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('phase_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignUlid('proposition_id')
                ->constrained()
                ->restrictOnDelete();
            $table->enum('kind', [
                'citation',
                'artefact',
                'hyperlink',
            ]);
            $table->text('reference');
            $table->enum('status', [
                'pending',
                'completed',
                'failed',
            ])->default('pending');
            $table->jsonb('input')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['phase_id', 'status']);
            $table->index(['proposition_id', 'status']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('instrument_execution_id')
                ->unique()
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
        Schema::dropIfExists('instrument_executions');
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('supersessions');

        Schema::table('phases', function (Blueprint $table) {
            $table->dropForeign(['gate_proposition_id']);
        });

        Schema::dropIfExists('propositions');
        Schema::dropIfExists('phases');
        Schema::dropIfExists('cycles');
        Schema::dropIfExists('participants');
    }
};
