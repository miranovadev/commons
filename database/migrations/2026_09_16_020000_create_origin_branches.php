<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('substrates', fn (Blueprint $table) => $table->unsignedBigInteger('field_version')->default(0));
        Schema::create('origin_branches', function (Blueprint $table): void {
            $table->foreignUlid('root_proposition_id')->primary()->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('origin_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->foreignUlid('substrate_id')->constrained()->restrictOnDelete();
            $table->timestampTz('opened_at');
            $table->timestampTz('pruned_at')->nullable();
            $table->index(['substrate_id', 'pruned_at']);
        });
        Schema::create('origin_branch_memberships', function (Blueprint $table): void {
            $table->foreignUlid('root_proposition_id')->constrained('origin_branches', 'root_proposition_id')->restrictOnDelete();
            $table->foreignUlid('proposition_id')->constrained()->restrictOnDelete();
            $table->primary(['root_proposition_id', 'proposition_id']);
            $table->index(['proposition_id', 'root_proposition_id']);
        });
        Schema::create('tragedies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('root_proposition_id')->unique()->constrained('origin_branches', 'root_proposition_id')->restrictOnDelete();
            $table->foreignUlid('substrate_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('participant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('phase_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('terminal_proposition_id')->constrained('propositions')->restrictOnDelete();
            $table->unsignedBigInteger('observed_field_version');
            $table->timestampTz('occurred_at');
            $table->json('evidence');
            $table->index(['substrate_id', 'occurred_at']);
        });
        // Migration-only projection. Runtime contributions copy the source's
        // indexed memberships; pruning never walks or updates a whole branch.
        DB::statement("INSERT INTO origin_branches (root_proposition_id, origin_proposition_id, substrate_id, opened_at)
            SELECT DISTINCT s.target_proposition_id, c.proposition_id, c.substrate_id, p.created_at
            FROM commitment_snapshots c JOIN supersessions s ON s.source_proposition_id = c.proposition_id
            JOIN propositions p ON p.id = s.target_proposition_id
            WHERE c.calculator_version = 'origin-bootstrap-v1'");
        DB::statement('WITH RECURSIVE members(root_proposition_id, proposition_id) AS (
            SELECT root_proposition_id, root_proposition_id FROM origin_branches
            UNION
            SELECT m.root_proposition_id, s.target_proposition_id FROM members m
            JOIN supersessions s ON s.source_proposition_id = m.proposition_id
        ) INSERT INTO origin_branch_memberships (root_proposition_id, proposition_id)
        SELECT root_proposition_id, proposition_id FROM members');
    }

    public function down(): void
    {
        Schema::dropIfExists('tragedies');
        Schema::dropIfExists('origin_branch_memberships');
        Schema::dropIfExists('origin_branches');
        Schema::table('substrates', fn (Blueprint $table) => $table->dropColumn('field_version'));
    }
};
