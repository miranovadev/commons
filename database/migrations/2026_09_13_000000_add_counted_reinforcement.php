<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['signature']);
            $table->index('signature');
        });

        // A source clock belongs to the server, never the submitting Participant.
        Schema::create('reinforcement_sources', function (Blueprint $table) {
            $table->string('resource_key', 64)->primary();
            $table->text('resource');
            $table->string('fingerprint', 64);
            $table->string('signature', 64);
            $table->timestamp('accepted_at');
            $table->string('pending_fingerprint', 64)->nullable();
            $table->timestamp('pending_since')->nullable();
        });

        Schema::create('reinforcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('proposition_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('participant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('receipt_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['proposition_id', 'participant_id']);
        });
        // Old Receipt signatures carry no proof of verification. Keep their history;
        // no automatic promotion or rewriting of immutable commitment snapshots.
    }

    public function down(): void
    {
        if (DB::table('receipts')->select('signature')->whereNotNull('signature')->groupBy('signature')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore unique Receipt signatures without destroying shared evidence history.');
        }
        Schema::dropIfExists('reinforcements');
        Schema::dropIfExists('reinforcement_sources');
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['signature']);
            $table->unique('signature');
        });
    }
};
