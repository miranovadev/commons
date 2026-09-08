<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attestations', function (Blueprint $table): void {
            $table->foreignUlid('supersedes_attestation_id')
                ->nullable()
                ->after('proposition_id')
                ->constrained('attestations')
                ->restrictOnDelete();
            $table->unique('supersedes_attestation_id');
            $table->unique(['phase_id', 'proposition_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attestations', function (Blueprint $table): void {
            $table->dropUnique(['phase_id', 'proposition_id']);
            $table->dropUnique(['supersedes_attestation_id']);
            $table->dropConstrainedForeignId('supersedes_attestation_id');
        });
    }
};
