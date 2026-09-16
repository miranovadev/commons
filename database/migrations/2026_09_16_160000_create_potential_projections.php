<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately schema-only. Existing substrates require explicit backfill.
        Schema::create('potential_projection_states', function (Blueprint $table): void {
            $table->ulid('substrate_id')->primary();
            $table->unsignedBigInteger('field_version')->nullable();
            $table->string('calculator_version')->nullable();
            $table->timestamp('projected_at')->nullable();
        });
        Schema::create('potential_dirty_horizons', function (Blueprint $table): void {
            $table->ulid('context_horizon_id')->primary();
            $table->ulid('substrate_id')->index();
            $table->boolean('measurements_dirty')->default(true);
        });
        Schema::create('potential_region_states', function (Blueprint $table): void {
            $table->ulid('context_horizon_id')->primary();
            $table->ulid('substrate_id')->index();
            $table->ulid('parent_context_horizon_id')->nullable()->index();
            $table->ulid('best_recovery_proposition_id')->nullable();
            $table->double('remaining_potential')->default(0);
            $table->unsignedBigInteger('field_version');
            $table->json('local_state');
            $table->json('frontier');
        });
        Schema::create('potential_region_dependencies', function (Blueprint $table): void {
            $table->ulid('context_horizon_id');
            $table->ulid('proposition_id');
            $table->primary(['context_horizon_id', 'proposition_id'], 'potential_dependency_primary');
            $table->index(['proposition_id', 'context_horizon_id'], 'potential_dependency_reverse');
        });
        Schema::create('potential_entries', function (Blueprint $table): void {
            $table->ulid('proposition_id')->primary();
            $table->ulid('context_horizon_id')->index();
            $table->ulid('substrate_id');
            $table->double('raw_potential_charge');
            $table->double('raw_charge');
            $table->index(['substrate_id', 'raw_potential_charge', 'raw_charge'], 'potential_entry_rank');
        });
        Schema::create('potential_branch_regions', function (Blueprint $table): void {
            $table->ulid('root_proposition_id');
            $table->ulid('context_horizon_id');
            $table->double('remaining_potential')->default(0);
            $table->boolean('complete');
            $table->unsignedInteger('candidate_count');
            $table->unsignedInteger('proposition_count');
            $table->primary(['root_proposition_id', 'context_horizon_id'], 'potential_branch_region_primary');
            $table->index(['root_proposition_id', 'remaining_potential'], 'potential_branch_viability');
            $table->index('context_horizon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potential_branch_regions');
        Schema::dropIfExists('potential_entries');
        Schema::dropIfExists('potential_region_dependencies');
        Schema::dropIfExists('potential_region_states');
        Schema::dropIfExists('potential_dirty_horizons');
        Schema::dropIfExists('potential_projection_states');
    }
};
