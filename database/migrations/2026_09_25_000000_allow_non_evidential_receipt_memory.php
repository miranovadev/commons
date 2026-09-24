<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_receipt_contexts', function (Blueprint $table): void {
            $table->string('evidence_signature', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('participant_receipt_contexts')->whereNull('evidence_signature')->exists()) {
            throw new RuntimeException('Cannot discard non-evidential Tragedy memory history.');
        }
        Schema::table('participant_receipt_contexts', function (Blueprint $table): void {
            $table->string('evidence_signature', 64)->nullable(false)->change();
        });
    }
};
