<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('context_horizons', function (Blueprint $table) {
            $table->foreignUlid('parent_context_horizon_id')->nullable()->constrained('context_horizons')->restrictOnDelete();
            $table->unique('opened_by_proposition_id');
        });
        Schema::table('cycles', function (Blueprint $table) {
            $table->foreignUlid('context_horizon_id')->nullable()->constrained()->restrictOnDelete();
        });

        $this->backfill();

        Schema::table('cycles', fn (Blueprint $table) => $table->ulid('context_horizon_id')->nullable(false)->change());
        DB::statement('CREATE UNIQUE INDEX context_horizons_one_root ON context_horizons (substrate_id) WHERE parent_context_horizon_id IS NULL');
        // Protect ownership from bulk updates as well as Eloquent saves.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION commons_cycle_horizon_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.context_horizon_id IS DISTINCT FROM OLD.context_horizon_id THEN
                        RAISE EXCEPTION 'A Cycle cannot change its Context Horizon';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER cycles_context_horizon_immutable BEFORE UPDATE OF context_horizon_id ON cycles
                FOR EACH ROW EXECUTE FUNCTION commons_cycle_horizon_immutable();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER cycles_context_horizon_immutable BEFORE UPDATE OF context_horizon_id ON cycles
                WHEN NEW.context_horizon_id IS NOT OLD.context_horizon_id
                BEGIN SELECT RAISE(ABORT, 'A Cycle cannot change its Context Horizon'); END;
                SQL);
        }
        DB::table('proposition_measurements')->update(['calculator_version' => null]);
    }

    /**
     * Legacy-only inference, before ownership becomes mandatory. Prefer the entry
     * Gate's opening boundary; otherwise use the original first Phase vocabulary
     * and entry instant. Never use the time of a later contribution. Ambiguous
     * history aborts the transactional migration rather than inventing ownership.
     */
    private function backfill(): void
    {
        foreach (DB::table('substrates')->orderBy('id')->get() as $substrate) {
            $horizons = DB::table('context_horizons')->where('substrate_id', $substrate->id)->orderBy('sequence')->get();
            if ($horizons->isEmpty() && ! DB::table('cycles')->join('participants', 'participants.id', '=', 'cycles.participant_id')->where('participants.substrate_id', $substrate->id)->exists()) {
                continue;
            }
            if ($horizons->isEmpty()) {
                $id = strtolower((string) Str::ulid());
                DB::table('context_horizons')->insert([
                    'id' => $id, 'substrate_id' => $substrate->id, 'sequence' => 1, 'domain' => 'emergence',
                    'opened_at' => $substrate->created_at, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $horizons = DB::table('context_horizons')->where('id', $id)->get();
            }
            $cycles = DB::table('cycles')->join('participants', 'participants.id', '=', 'cycles.participant_id')
                ->where('participants.substrate_id', $substrate->id)->select('cycles.*')->orderBy('cycles.created_at')->orderBy('cycles.id')->get();
            foreach ($cycles as $cycle) {
                $first = DB::table('phases')->where('cycle_id', $cycle->id)->orderBy('kind')->orderBy('id')->first();
                $began = $first?->started_at ?? $cycle->created_at;
                $domain = $first === null ? null : (in_array($first->write_register, ['activity', 'frontier', 'canon'], true) ? 'emergence' : 'settlement');
                $eligible = $horizons->filter(fn ($h) => ($domain === null || $h->domain === $domain) && $h->opened_at <= $began);
                $openedByGate = $first?->gate_proposition_id === null ? collect() : $eligible->where('opened_by_proposition_id', $first->gate_proposition_id);
                $candidates = $openedByGate->isNotEmpty() ? $openedByGate : $eligible->filter(fn ($h) => $h->closed_at === null || $h->closed_at >= $began);
                $ownsOrigin = DB::table('propositions as p')->join('phases', 'phases.id', '=', 'p.phase_id')
                    ->join('substrate_ledger_entries as e', 'e.ledgerable_id', '=', 'p.id')
                    ->where('phases.cycle_id', $cycle->id)->where('e.substrate_id', $substrate->id)
                    ->where('e.kind', 'proposition')->where('e.sequence', 1)->exists();
                if ($ownsOrigin) {
                    $candidates = $horizons->where('sequence', 1);
                }
                if ($candidates->count() !== 1) {
                    throw new RuntimeException("Cannot unambiguously backfill Context Horizon for Cycle {$cycle->id}; inspect its entry Phase and boundary history before retrying.");
                }
                DB::table('cycles')->where('id', $cycle->id)->update(['context_horizon_id' => $candidates->first()->id]);
            }
            // Correct contaminated memberships using the owner, including writes
            // made after another participant opened a newer Horizon.
            $nodes = DB::table('context_horizon_memberships as m')->join('propositions as p', 'p.id', '=', 'm.proposition_id')
                ->join('phases as phase', 'phase.id', '=', 'p.phase_id')->join('cycles as cycle', 'cycle.id', '=', 'phase.cycle_id')
                ->whereIn('m.context_horizon_id', $horizons->pluck('id'))->select('m.proposition_id', 'cycle.context_horizon_id')->get();
            foreach ($nodes as $node) {
                DB::table('context_horizon_memberships')->where('proposition_id', $node->proposition_id)->update(['context_horizon_id' => $node->context_horizon_id]);
            }
            foreach ($horizons as $horizon) {
                if ($horizon->opened_by_proposition_id === null) {
                    if ($horizon->sequence !== 1 || $horizon->domain !== 'emergence') {
                        throw new RuntimeException("Invalid root Context Horizon {$horizon->id}.");
                    }

                    continue;
                }
                $parentId = DB::table('propositions as p')->join('phases', 'phases.id', '=', 'p.phase_id')
                    ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')->where('p.id', $horizon->opened_by_proposition_id)->value('cycles.context_horizon_id');
                $parent = $horizons->firstWhere('id', $parentId);
                $snapshot = DB::table('commitment_snapshots')->where('proposition_id', $horizon->opened_by_proposition_id)->first();
                if ($parent === null || $parent->sequence >= $horizon->sequence || $parent->domain === $horizon->domain
                    || $snapshot?->direction_before !== $parent->domain || $snapshot?->direction_after !== $horizon->domain) {
                    throw new RuntimeException("Invalid causal boundary for Context Horizon {$horizon->id}; immutable snapshot history needs review.");
                }
                DB::table('context_horizons')->where('id', $horizon->id)->update(['parent_context_horizon_id' => $parentId]);
            }
            // A legacy global closing marker may belong to a different region.
            // Rebuild only this summary from explicit child boundary relationships.
            foreach ($horizons as $horizon) {
                $child = DB::table('context_horizons')->where('parent_context_horizon_id', $horizon->id)->orderBy('sequence')->first();
                DB::table('context_horizons')->where('id', $horizon->id)->update([
                    'closed_at' => $child?->opened_at, 'closed_by_proposition_id' => $child?->opened_by_proposition_id,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS cycles_context_horizon_immutable ON cycles; DROP FUNCTION IF EXISTS commons_cycle_horizon_immutable();');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS cycles_context_horizon_immutable');
        }
        DB::statement('DROP INDEX IF EXISTS context_horizons_one_root');
        Schema::table('cycles', fn (Blueprint $table) => $table->dropConstrainedForeignId('context_horizon_id'));
        Schema::table('context_horizons', function (Blueprint $table) {
            $table->dropUnique(['opened_by_proposition_id']);
            $table->dropConstrainedForeignId('parent_context_horizon_id');
        });
    }
};
