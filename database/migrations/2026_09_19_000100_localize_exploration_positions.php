<?php

use App\Services\ExplorationPositionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Schema::create('local_exploration_states', function (Blueprint $table): void {
                $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
                $table->unsignedTinyInteger('structural_position');
                $table->foreignUlid('structural_source_id')->constrained('propositions')->restrictOnDelete();
                $table->unsignedBigInteger('epoch')->default(0);
                $table->primary(['context_horizon_id', 'structural_position', 'structural_source_id'], 'local_exploration_position');
            });
            $legacy = DB::table('horizon_exploration_states')->get()
                ->keyBy(fn ($row) => $row->context_horizon_id.':'.$row->structural_position);
            $positions = new ExplorationPositionService;
            DB::table('propositions')->where('tier', 'expressive')->orderBy('id')->chunkById(500, function ($nodes) use ($positions, $legacy): void {
                $resolved = $positions->forIds($nodes->pluck('id'));
                $unknown = $resolved->filter(fn ($position) => $position === null)->keys();
                if ($unknown->isNotEmpty() && DB::table('continuation_failures')->whereIn('proposition_id', $unknown)->exists()) {
                    throw new RuntimeException('Cannot localize failed exploration with ambiguous provenance. Audit the affected propositions before retrying.');
                }
                foreach ($resolved->filter() as $position) {
                    $previous = $legacy->get($position['context_horizon_id'].':'.$position['structural_position']);
                    if ($previous !== null) {
                        // Preserve the cutover epoch labels and existing failure applicability.
                        // Future advances are independent; do not renumber immutable old evidence.
                        DB::table('local_exploration_states')->insertOrIgnore([...$position, 'epoch' => $previous->epoch]);
                    }
                }
            });
            Schema::drop('horizon_exploration_states');
            Schema::rename('local_exploration_states', 'horizon_exploration_states');
        });
    }

    public function down(): void
    {
        if (DB::table('horizon_exploration_states')->exists() || DB::table('continuation_failures')->exists()) {
            throw new RuntimeException('Cannot collapse local exploration history into Horizon-wide epochs. Restore a pre-rollout backup instead.');
        }
        Schema::drop('horizon_exploration_states');
        Schema::create('horizon_exploration_states', function (Blueprint $table): void {
            $table->foreignUlid('context_horizon_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('structural_position');
            $table->unsignedBigInteger('epoch')->default(0);
            $table->primary(['context_horizon_id', 'structural_position'], 'horizon_exploration_position');
        });
    }
};
