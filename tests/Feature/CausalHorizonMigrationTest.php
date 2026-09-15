<?php

namespace Tests\Feature;

use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CausalHorizonMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const string MIGRATION = 'migrations/2026_09_13_120000_bind_cycles_to_causal_context_horizons.php';

    private string $substrateId;

    private int $sequence = 0;

    protected function afterRefreshingDatabase(): void
    {
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_09_15_120000_protect_causal_ownership.php'])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--path' => 'database/'.self::MIGRATION])->assertSuccessful();
    }

    public function test_legacy_cycles_are_backfilled_and_late_memberships_are_repaired_without_changing_snapshots(): void
    {
        [$root, $h2, $h3, $originCycle, $a, $b, $late] = $this->legacyHistory();
        $snapshots = DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->toJson();
        $this->migrateOwnership();
        $this->assertSame($root, DB::table('cycles')->where('id', $originCycle)->value('context_horizon_id'));
        foreach ([$a, $b] as $cycle) {
            $this->assertSame($h2, DB::table('cycles')->where('id', $cycle)->value('context_horizon_id'));
        }
        $this->assertSame($h2, ContextHorizonMembership::findOrFail($late)->context_horizon_id);
        $this->assertSame($root, DB::table('context_horizons')->where('id', $h2)->value('parent_context_horizon_id'));
        $this->assertSame($h2, DB::table('context_horizons')->where('id', $h3)->value('parent_context_horizon_id'));
        $this->assertSame($snapshots, DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->toJson());
        $this->assertNull(DB::table('cycles')->where('id', $a)->value('completed_at'));
        $this->assertDatabaseCount('context_horizons', 3);
        $this->assertDatabaseCount('context_horizon_memberships', 3);
        $this->assertSame(0, DB::table('cycles')->whereNull('context_horizon_id')->count());
        $this->assertNotNull(DB::table('context_horizons')->where('id', $h2)->value('closed_at'));
    }

    public function test_ambiguous_legacy_entry_is_reported_instead_of_using_the_latest_horizon(): void
    {
        $this->legacyHistory();
        [, $cycle] = $this->legacyPhase('signal', '2026-09-13 00:00:00'); // Before any Settlement region exists.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot unambiguously backfill Context Horizon for Cycle {$cycle}");
        $this->migrateOwnership();
    }

    private function migrateOwnership(): void
    {
        try {
            // Exercise the real migrator, including PostgreSQL's DDL transaction
            // and the migration record used by DatabaseMigrations at teardown.
            app('migrator')->run([database_path(self::MIGRATION)]);
        } catch (\Throwable $exception) {
            // SQLite does not wrap Laravel migrations in schema transactions.
            // Remove its partial schema before the remaining legacy rollback.
            if (! DB::getSchemaGrammar()->supportsSchemaTransactions()
                && Schema::hasColumn('cycles', 'context_horizon_id')) {
                (require database_path(self::MIGRATION))->down();
            }

            throw $exception;
        }
    }

    private function legacyHistory(): array
    {
        $this->substrateId = $this->id();
        DB::table('substrates')->insert(['id' => $this->substrateId, 'slug' => 'legacy', 'title' => 'Legacy',
            'created_at' => '2026-09-13 00:00:00', 'updated_at' => '2026-09-13 00:00:00']);
        [$phase, $originCycle] = $this->legacyPhase('canon', '2026-09-13 00:00:00');
        [$origin, $originEntry] = $this->legacyNode($phase, 'canon', '2026-09-13 00:00:10', true);
        $this->legacySnapshot($origin, $originEntry, 'emergence', 'settlement', '2026-09-13 00:01:00');
        $root = $this->legacyHorizon(1, 'emergence', null, '2026-09-13 00:00:00', $origin, '2026-09-13 00:01:00');
        $h2 = $this->legacyHorizon(2, 'settlement', $origin, '2026-09-13 00:01:00');
        $this->membership($origin, $originEntry, $root);
        [$phaseB, $b] = $this->legacyPhase('concord', '2026-09-13 00:01:10', $origin);
        [$phaseA, $a] = $this->legacyPhase('signal', '2026-09-13 00:01:20', $origin);
        [$commit, $commitEntry] = $this->legacyNode($phaseB, 'concord', '2026-09-13 00:02:00', true);
        $this->legacySnapshot($commit, $commitEntry, 'settlement', 'emergence', '2026-09-13 00:02:00');
        $h3 = $this->legacyHorizon(3, 'emergence', $commit, '2026-09-13 00:02:00');
        DB::table('context_horizons')->where('id', $h2)->update(['closed_at' => '2026-09-13 00:02:00', 'closed_by_proposition_id' => $commit]);
        $this->membership($commit, $commitEntry, $h2);
        $latePhase = $this->id();
        DB::table('phases')->insert(['id' => $latePhase, 'cycle_id' => $a, 'kind' => 5,
            'write_register' => 'stance', 'started_at' => '2026-09-13 00:03:00', 'created_at' => '2026-09-13 00:03:00']);
        [$late, $lateEntry] = $this->legacyNode($latePhase, 'stance', '2026-09-13 00:03:00');
        $this->membership($late, $lateEntry, $h3); // Production contamination.

        return [$root, $h2, $h3, $originCycle, $a, $b, $late];
    }

    private function legacyPhase(string $register, string $at, ?string $gate = null): array
    {
        $participant = $this->id();
        $cycle = $this->id();
        $phase = $this->id();
        DB::table('participants')->insert(['id' => $participant, 'substrate_id' => $this->substrateId,
            'callsign' => $participant, 'created_at' => $at, 'updated_at' => $at]);
        DB::table('cycles')->insert(['id' => $cycle, 'participant_id' => $participant, 'sequence' => 1, 'created_at' => $at, 'updated_at' => $at]);
        DB::table('phases')->insert(['id' => $phase, 'cycle_id' => $cycle, 'kind' => $register === 'signal' ? 1 : 7,
            'write_register' => $register, 'gate_proposition_id' => $gate, 'started_at' => $at, 'created_at' => $at, 'updated_at' => $at]);

        return [$phase, $cycle];
    }

    private function legacyNode(string $phase, string $register, string $at, bool $committal = false): array
    {
        $id = $this->id();
        $entry = $this->id();
        DB::table('propositions')->insert(['id' => $id, 'phase_id' => $phase, 'register' => $register,
            'tier' => $committal ? 'committal' : 'expressive', 'statement' => 'Historical fixture', 'created_at' => $at, 'updated_at' => $at]);
        DB::table('substrate_ledger_entries')->insert(['id' => $entry, 'substrate_id' => $this->substrateId,
            'sequence' => ++$this->sequence, 'kind' => 'proposition', 'ledgerable_type' => (new Proposition)->getMorphClass(),
            'ledgerable_id' => $id, 'recorded_at' => $at]);

        return [$id, $entry];
    }

    private function legacySnapshot(string $node, string $entry, string $before, string $after, string $at): void
    {
        DB::table('commitment_snapshots')->insert(['proposition_id' => $node, 'substrate_id' => $this->substrateId,
            'ledger_entry_id' => $entry, 'correspondence' => -.5, 'chirality' => -.5, 'closure' => -.5,
            'prominence' => $after === 'emergence' ? .75 : .25, 'turbulence' => $after === 'emergence' ? .25 : .75, 'tension' => $after === 'emergence' ? .5 : -.5,
            'direction_before' => $before, 'direction_after' => $after, 'calculator_version' => 'legacy', 'evaluated_at' => $at]);
    }

    private function legacyHorizon(int $sequence, string $domain, ?string $opener, string $at, ?string $closer = null, ?string $closedAt = null): string
    {
        $id = $this->id();
        DB::table('context_horizons')->insert(['id' => $id, 'substrate_id' => $this->substrateId, 'sequence' => $sequence,
            'domain' => $domain, 'opened_by_proposition_id' => $opener, 'opened_at' => $at,
            'closed_by_proposition_id' => $closer, 'closed_at' => $closedAt, 'created_at' => $at, 'updated_at' => $at]);

        return $id;
    }

    private function membership(string $node, string $entry, string $horizon): void
    {
        DB::table('context_horizon_memberships')->insert(['proposition_id' => $node, 'ledger_entry_id' => $entry,
            'context_horizon_id' => $horizon, 'projected_at' => now()]);
    }

    private function id(): string
    {
        return strtolower((string) str()->ulid());
    }
}
