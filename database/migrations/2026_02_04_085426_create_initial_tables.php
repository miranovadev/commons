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
        Schema::create('substrates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('visibility', ['public', 'private'])
                ->default('private');
            $table->jsonb('configuration')->default('{}');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('scope', ['system', 'substrate']);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('substrate_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('callsign');
            $table->string('designation')->nullable();
            $table->unsignedSmallInteger('max_read_count')->default(25);
            $table->unsignedInteger('max_token_count')->nullable();
            $table->timestamps();

            $table->unique(['substrate_id', 'callsign']);
            $table->unique(['substrate_id', 'user_id']);
        });

        Schema::create('substrate_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('substrate_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignUlid('participant_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->enum('status', ['invited', 'active'])
                ->default('invited');
            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['substrate_id', 'user_id']);
            $table->unique(['substrate_id', 'participant_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUlid('substrate_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'user_id', 'substrate_id']);
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

        Schema::create('participant_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('provider', ['openai', 'anthropic']);
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->jsonb('configuration')->default('{}');
            $table->text('credentials');
            $table->timestampTz('next_trigger_at')->nullable();
            $table->timestampTz('last_triggered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'provider', 'name']);
            $table->index(['provider', 'is_enabled']);
            $table->index(['is_enabled', 'next_trigger_at']);
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

        Schema::create('evidence_executions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cycle_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['instrument', 'prepare'])->default('instrument');
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
            ])->nullable();
            $table->text('reference')->nullable();
            $table->enum('status', [
                'pending',
                'presented',
                'completed',
                'failed',
            ])->default('pending');
            $table->jsonb('input')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->jsonb('presentation')->nullable();
            $table->enum('outcome', ['contributed', 'deferred'])->nullable();
            $table->foreignUlid('resulting_proposition_id')->nullable()->constrained('propositions')->restrictOnDelete();
            $table->timestamp('presented_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['cycle_id', 'type', 'status']);

            $table->index(['phase_id', 'status']);
            $table->index(['proposition_id', 'status']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_execution_id')
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
        Schema::dropIfExists('evidence_executions');
        Schema::dropIfExists('attestations');
        Schema::dropIfExists('supersessions');

        Schema::table('phases', function (Blueprint $table) {
            $table->dropForeign(['gate_proposition_id']);
        });

        Schema::dropIfExists('propositions');
        Schema::dropIfExists('phases');
        Schema::dropIfExists('participant_connections');
        Schema::dropIfExists('cycles');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('substrate_memberships');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('substrates');
    }
};
