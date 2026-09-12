<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_region_polarities', function (Blueprint $table) {
            $table->foreignUlid('proposition_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('governing_direction');
            $table->string('support_direction');
        });
        Schema::table('propositions', fn (Blueprint $table) => $table->string('correspondence_direction')->nullable());
        DB::statement(<<<'SQL'
            UPDATE propositions SET correspondence_direction = (
                SELECT CASE WHEN m.correspondence > 0 THEN 'positive'
                    WHEN m.correspondence < 0 THEN 'negative' ELSE NULL END
                FROM proposition_measurements m WHERE m.proposition_id = propositions.id
            )
            SQL);

        // No proposition-level Attestation/Lean state is stored. Historical evidence
        // and aggregate directions are replayable from the contribution ledger.
        App\Models\Proposition::query()->where('tier', 'committal')->orderBy('id')->each(
            fn ($seed) => app(App\Services\CommitRegionPolarityService::class)->backfill($seed),
        );
        DB::table('proposition_measurements')->update(['calculator_version' => null]);
    }

    public function down(): void
    {
        Schema::table('propositions', fn (Blueprint $table) => $table->dropColumn('correspondence_direction'));
        Schema::dropIfExists('commit_region_polarities');
    }
};
