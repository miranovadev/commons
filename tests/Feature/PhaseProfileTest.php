<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\ClosureBirthService;
use App\Services\Observables\PhaseProfileObservationService;
use App\Services\Observables\PhaseProfileService;
use App\Services\PropositionMeasurementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PhaseProfileTest extends TestCase
{
    use RefreshDatabase;

    private Participant $actor;

    private Proposition $origin;

    private Cycle $firstCycle;

    protected function setUp(): void
    {
        parent::setUp();
        $s = (new Substrate)->forceFill(['slug' => 'phase-profile', 'title' => 'Phase Profile', 'visibility' => 'private', 'configuration' => []]);
        $s->save();
        $this->actor = (new Participant)->forceFill(['substrate_id' => $s->id, 'callsign' => 'observer']);
        $this->actor->save();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
        $this->origin = $this->actor->fresh()->position;
        $this->firstCycle = $this->actor->cycles()->latest('sequence')->firstOrFail();
    }

    private function cycle(?Proposition $entry = null, ?Participant $actor = null): Cycle
    {
        $entry ??= $this->origin;
        $actor ??= $this->actor;
        $cycle = (new Cycle)->forceFill(['participant_id' => $actor->id,
            'sequence' => (int) $actor->cycles()->max('sequence') + 1,
            'context_horizon_id' => app(ContextHorizonProjector::class)->forEntry($entry)->id,
            'entry_committal_id' => $entry->id]);
        $cycle->save();

        return $cycle;
    }

    private function phase(Cycle $cycle, PhaseKind $kind, Proposition $gate): Phase
    {
        return $cycle->phases()->where('kind', $kind->value)->first() ?? tap(
            (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind,
                'gate_proposition_id' => $gate->id, 'write_register' => $cycle->contextHorizon->domain->registerFor($kind), 'started_at' => now()]),
            fn ($p) => $p->save(),
        );
    }

    /** Construct the actual operation's edges, not a generic Variant at every Phase. */
    private function born(Cycle $cycle, PhaseKind $kind, Proposition $source, string $type = 'variant', ?Proposition $other = null, ?Proposition $actingGate = null): Proposition
    {
        return DB::transaction(function () use ($cycle, $kind, $source, $type, $other, $actingGate) {
            $phase = $this->phase($cycle, $kind, $actingGate ?? $source);
            $node = (new Proposition)->forceFill(['phase_id' => $phase->id,
                'tier' => $type === 'commit' ? 'committal' : 'expressive',
                'register' => $phase->write_register, 'statement' => 'Constructed causal observation']);
            $node->save();
            app(LedgerService::class)->record($node, $source);
            foreach (array_filter([$source, $other]) as $parent) {
                $edge = (new Supersession)->forceFill(['phase_id' => $phase->id,
                    'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => $type]);
                $edge->save();
                app(LedgerService::class)->record($edge);
            }

            return $node;
        });
    }

    private function throughDisperse(Cycle $cycle): Proposition
    {
        $p1 = $this->born($cycle, PhaseKind::Distinguish, $cycle->entryCommittal);
        $p2 = $this->born($cycle, PhaseKind::Persist, $p1);
        $p3 = $this->born($cycle, PhaseKind::Constrain, $p2, 'reclassify');

        return $this->born($cycle, PhaseKind::Disperse, $p3);
    }

    private function throughMediate(Cycle $cycle): Proposition
    {
        $gate = $this->throughDisperse($cycle);
        // A separate producing Cycle authors the alternative Disperse branch.
        $other = $this->born($this->cycle($cycle->entryCommittal), PhaseKind::Disperse, $gate->parents()->sole());

        return $this->born($cycle, PhaseKind::Mediate, $gate, 'reconcile', $other);
    }

    private function throughBias(Cycle $cycle): Proposition
    {
        return $this->born($cycle, PhaseKind::Bias, $this->throughMediate($cycle), 'reclassify');
    }

    /** A complete structural Commit history with genuine Order source/edge and frozen birth state. */
    private function basin(bool $boundary = false): Proposition
    {
        $cycle = $this->cycle();
        $source = $this->throughBias($cycle);
        $commit = $this->born($cycle, PhaseKind::Order, $source, 'commit');
        $h = $cycle->contextHorizon;
        $birth = app(ClosureBirthService::class)->stored($commit);
        $snapshot = (new CommitmentSnapshot)->forceFill(['proposition_id' => $commit->id,
            'substrate_id' => $this->actor->substrate_id, 'ledger_entry_id' => $commit->ledgerEntry->id,
            'supersession_id' => Supersession::where('target_proposition_id', $commit->id)->sole()->id,
            'correspondence' => 0, 'chirality' => 0, 'closure' => $birth['closure'],
            'cadence_interval' => $birth['cadence_interval'], 'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $h->domain, 'direction_after' => $boundary ? $h->domain->opposite() : $h->domain,
            'calculator_version' => 'phase-profile-structural-fixture', 'evaluated_at' => now()]);
        $snapshot->save();
        app(ContextHorizonProjector::class)->apply($snapshot);

        return $commit;
    }

    private function observation(Proposition $p): array
    {
        $o = app(PhaseProfileObservationService::class)->stored($p->id);
        $this->assertNotNull($o);

        return ['coordinate' => $o->birth_cycle_coordinate === null ? null : (int) $o->birth_cycle_coordinate,
            'reference' => $o->preceding_phase_proposition_id,
            'interval' => $o->cycle_interval === null ? null : (int) $o->cycle_interval,
            'reason' => $o->unavailable_reason];
    }

    private function profile(Proposition $p): array
    {
        return app(PhaseProfileService::class)->profile($p);
    }

    public function test_first_expressive_birth_records_coordinate_without_inventing_interval(): void
    {
        $p = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $this->assertSame(['coordinate' => 1, 'reference' => null, 'interval' => null,
            'reason' => 'first_expressive_observation'], $this->observation($p));
        $this->assertSame(array_fill(0, 7, null), $this->profile($p)['means']);
        $this->assertSame(array_fill(0, 7, 0), $this->profile($p)['sample_counts']);
    }

    public function test_same_basin_branches_share_clock_but_not_observations(): void
    {
        $a = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $other = (new Participant)->forceFill(['substrate_id' => $this->actor->substrate_id, 'callsign' => 'other']);
        $other->save();
        $bCycle = $this->cycle(actor: $other);
        $b = $this->born($bCycle, PhaseKind::Distinguish, $this->origin);
        $b2 = $this->born($bCycle, PhaseKind::Persist, $b);
        $a2 = $this->born($this->firstCycle, PhaseKind::Persist, $a);
        $this->assertSame(1, (int) $bCycle->sequence);
        $this->assertSame(2, $this->observation($a2)['coordinate']);
        $this->assertSame(1, $this->observation($a2)['interval']);
        $this->assertEqualsCanonicalizing([$a->id, $a2->id], array_column($this->profile($a2)['observations'], 'proposition_id'));
        $this->assertNotContains($b2->id, array_column($this->profile($a2)['observations'], 'proposition_id'));
        $this->assertSame([null, 1.0, null, null, null, null, null], $this->profile($a2)['means']);
    }

    public function test_different_basins_in_same_horizon_have_independent_clocks(): void
    {
        $a = $this->basin();
        $b = $this->basin();
        $ca = $this->cycle($a);
        $cb = $this->cycle($b);
        $this->assertSame($ca->context_horizon_id, $cb->context_horizon_id);
        for ($i = 0; $i < 4; $i++) {
            $this->cycle($b);
        }
        $p = $this->born($ca, PhaseKind::Distinguish, $a);
        $q = $this->born($cb, PhaseKind::Distinguish, $b);
        $this->assertSame(1, $this->observation($p)['coordinate']);
        $this->assertSame(5, $this->observation($q)['coordinate']);
        $this->assertNull($this->observation($p)['interval']);
    }

    public function test_revisions_keep_preceding_phase_reference_and_mean_uses_all_genuine_samples(): void
    {
        for ($i = 1; $i < 10; $i++) {
            $this->cycle();
        }
        $p1 = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $p2 = $this->born($this->cycle(), PhaseKind::Persist, $p1);
        $r1 = $this->born($this->cycle(), PhaseKind::Persist, $p2, 'revise', actingGate: $p1);
        $r2 = $this->born($this->cycle(), PhaseKind::Persist, $r1, 'revise', actingGate: $p1);
        $this->assertSame([10, 11, 12, 13], array_map(fn ($p) => $this->observation($p)['coordinate'], [$p1, $p2, $r1, $r2]));
        $this->assertSame([1, 2, 3], array_map(fn ($p) => $this->observation($p)['interval'], [$p2, $r1, $r2]));
        foreach ([$p2, $r1, $r2] as $p) {
            $this->assertSame($p1->id, $this->observation($p)['reference']);
        }
        $this->assertSame(2.0, $this->profile($r2)['means'][1]);
        $this->assertSame(3, $this->profile($r2)['sample_counts'][1]);
    }

    public function test_genuine_zero_is_a_sample_and_one_interval_is_mature(): void
    {
        $p1 = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $p2 = $this->born($this->firstCycle, PhaseKind::Persist, $p1);
        $this->assertSame(0, $this->observation($p2)['interval']);
        $this->assertSame(0.0, $this->profile($p2)['means'][1]);
        $this->assertSame(1, $this->profile($p2)['sample_counts'][1]);
        $diagnostic = app(PhaseProfileService::class)->inspect($p2);
        $this->assertSame([0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0], $diagnostic['contributions']);
        $this->assertEqualsWithDelta(1 / 7, $diagnostic['phase_profile'], 1e-12);
    }

    public function test_origin_is_constitutional_without_empirical_rows(): void
    {
        $this->assertDatabaseMissing('phase_profile_observations', ['proposition_id' => $this->origin->id]);
        $p = $this->profile($this->origin);
        $this->assertSame('constitutional_origin', $p['kind']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0], $p['means']);
        $this->assertSame([], $p['observations']);
        $this->assertSame(1.0, (float) $this->origin->commitmentSnapshot->correspondence);
    }

    public static function reconcileGates(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('reconcileGates')]
    public function test_reconcile_uses_acting_gate_and_keeps_both_causal_parents(bool $reverse): void
    {
        // Reverse in a fresh fixture: do not reconcile already-consumed parents again.
        $a = $this->throughDisperse($this->firstCycle);
        $cb = $this->cycle();
        $b = $this->throughDisperse($cb);
        $unselected = $this->throughDisperse($this->cycle());
        $gate = $reverse ? $b : $a;
        $operand = $reverse ? $a : $b;
        $r = $this->born($reverse ? $cb : $this->firstCycle, PhaseKind::Mediate, $gate, 'reconcile', $operand);
        $this->assertSame($reverse ? 1 : 2, $this->observation($r)['interval']);
        $this->assertSame($gate->id, $this->observation($r)['reference']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $r->parents()->pluck('propositions.id')->all());
        $ids = array_column($this->profile($r)['observations'], 'proposition_id');
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->assertNotContains($unselected->id, $ids);
        $this->assertSame(count($ids), count(array_unique($ids)));
    }

    public function test_foreign_bias_has_null_interval_and_order_can_use_its_local_coordinate(): void
    {
        $a = $this->basin();
        $b = $this->basin();
        $foreignCycle = $this->cycle($a);
        $foreign = $this->throughMediate($foreignCycle);
        $localCycle = $this->cycle($b);
        $local = $this->throughMediate($localCycle);
        $first = $this->born($localCycle, PhaseKind::Bias, $foreign, 'reclassify', actingGate: $local);
        $this->assertSame($b->id, $first->phase->cycle->entry_committal_id);
        $this->assertSame(['coordinate' => 2, 'reference' => null, 'interval' => null, 'reason' => 'no_local_predecessor'], $this->observation($first));
        for ($i = 0; $i < 5; $i++) {
            $this->cycle($a);
        }
        $second = $first; // Later foreign entries cannot change the local observation.
        $this->assertSame($this->observation($first), $this->observation($second));
        $this->assertSame([$foreign->id], $second->parents()->pluck('propositions.id')->all());
        $this->assertFalse($second->parents()->where('propositions.id', $local->id)->exists());
        $before = $this->profile($second);
        for ($i = 0; $i < 4; $i++) {
            $this->cycle($b);
        }
        $preview = app(PhaseProfileService::class)->inspect($second)['prospective_order'];
        $this->assertSame(4, $preview['cycle_interval']);
        $commit = $this->born($localCycle, PhaseKind::Order, $second, 'commit');
        $this->assertSame(4, $this->observation($commit)['interval']);
        $this->assertSame($second->id, $this->observation($commit)['reference']);
        $this->assertSame($before, $this->profile($second));
    }

    public function test_foreign_reconcile_operand_does_not_supply_local_interval(): void
    {
        $a = $this->basin();
        $b = $this->basin();
        $ca = $this->cycle($a);
        $cb = $this->cycle($b);
        $foreign = $this->throughDisperse($ca);
        $local = $this->throughDisperse($cb);
        $this->cycle($b);
        for ($i = 0; $i < 8; $i++) {
            $this->cycle($a);
        }
        $r = $this->born($cb, PhaseKind::Mediate, $local, 'reconcile', $foreign);
        $this->assertSame(2, $this->observation($r)['coordinate']);
        $this->assertSame(1, $this->observation($r)['interval']);
        $this->assertSame($local->id, $this->observation($r)['reference']);
        $this->assertCount(2, $r->parents()->get());
    }

    public function test_completed_reference_is_the_opening_boundary_lineage_not_later_siblings(): void
    {
        $boundary = $this->basin(boundary: true);
        $childCycle = $this->cycle($boundary);
        $head = $this->born($childCycle, PhaseKind::Distinguish, $boundary);
        $before = app(PhaseProfileService::class)->inspect($head);
        $sibling = $this->throughBias($this->cycle());
        $after = app(PhaseProfileService::class)->inspect($head);
        $this->assertSame($before, $after);
        $this->assertSame($boundary->id, $after['reference']['head_id']);
        $this->assertNotContains($sibling->id, array_column($after['reference']['observations'], 'proposition_id'));
        $this->assertSame([$head->id], array_column($after['current']['observations'], 'proposition_id'));
    }

    public function test_same_phase_revision_cannot_repair_missing_original_reference(): void
    {
        $p1 = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $p2 = $this->born($this->firstCycle, PhaseKind::Persist, $p1);
        DB::table('phase_profile_observations')->where('proposition_id', $p2->id)->delete();
        $revision = $this->born($this->cycle(), PhaseKind::Persist, $p2, 'revise', actingGate: $p1);
        $this->assertSame('missing_reference_history', $this->observation($revision)['reason']);
        $this->assertNull($this->observation($revision)['interval']);
        $this->assertNull($this->observation($revision)['reference']);
    }

    public function test_missing_historical_stamp_is_not_reconstructed_or_averaged_as_zero(): void
    {
        $p1 = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        DB::table('phase_profile_observations')->where('proposition_id', $p1->id)->delete();
        app(LedgerService::class)->record($p1, $this->origin);
        $this->assertDatabaseMissing('phase_profile_observations', ['proposition_id' => $p1->id]);
        $p2 = $this->born($this->firstCycle, PhaseKind::Persist, $p1);
        $this->assertSame('missing_reference_history', $this->observation($p2)['reason']);
        $this->assertNull($this->observation($p2)['interval']);
        $this->assertFalse($this->profile($p2)['history_complete']);
        $this->assertSame(array_fill(0, 7, null), $this->profile($p2)['means']);
    }

    public function test_historical_cycle_without_entry_marker_is_explicitly_unavailable(): void
    {
        DB::table('cycles')->where('id', $this->firstCycle->id)->update(['chirality_entry_after_sequence' => null]);
        $p = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $this->assertNull($this->observation($p)['coordinate']);
        $this->assertSame('missing_cycle_history', $this->observation($p)['reason']);
    }

    public function test_later_cycles_replay_and_projection_rebuild_cannot_rewrite_birth(): void
    {
        $p = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $birth = $this->observation($p);
        $this->cycle();
        app(LedgerService::class)->record($p, $this->origin);
        app(ContextHorizonProjector::class)->rebuild($this->actor->substrate);
        $this->assertSame($birth, $this->observation($p));
    }

    public static function immutableFields(): array
    {
        return [['birth_cycle_coordinate', 99], ['cycle_interval', 99], ['unavailable_reason', 'changed']];
    }

    #[DataProvider('immutableFields')]
    public function test_birth_observation_is_database_immutable(string $column, mixed $value): void
    {
        $p = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $this->expectException(QueryException::class);
        DB::table('phase_profile_observations')->where('proposition_id', $p->id)->update([$column => $value]);
    }

    public function test_real_dispatch_passes_authorized_source(): void
    {
        $phase = $this->firstCycle->phases()->sole();
        $p = app(ContributionDispatcher::class)->dispatch($this->actor, $phase,
            ['action' => 'proposition.variant', 'statement' => 'A real authored distinction']);
        $this->assertSame('first_expressive_observation', $this->observation($p)['reason']);
        $this->assertSame([$this->origin->id], $p->parents()->pluck('propositions.id')->all());
    }

    public function test_migration_is_reversible_and_does_not_backfill_history(): void
    {
        $p = $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
        $before = $this->origin->commitmentSnapshot->getAttributes();
        $migration = require database_path('migrations/2026_10_11_000000_create_phase_profile_observations.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('phase_profile_observations', 0);
        app(LedgerService::class)->record($p, $this->origin);
        $this->assertDatabaseCount('phase_profile_observations', 0);
        $this->assertFalse($this->profile($p)['history_complete']);
        $this->assertSame($before, $this->origin->commitmentSnapshot->fresh()->getAttributes());
    }

    public function test_failed_observation_rolls_back_the_atomic_write(): void
    {
        $count = Proposition::count();
        $ledgerCount = DB::table('substrate_ledger_entries')->count();
        $this->mock(PhaseProfileObservationService::class, function ($mock): void {
            $mock->shouldReceive('recordBirth')->once()->andThrow(new RuntimeException('observation failed'));
        });
        try {
            $this->born($this->firstCycle, PhaseKind::Distinguish, $this->origin);
            $this->fail('Birth must roll back');
        } catch (RuntimeException $e) {
            $this->assertSame('observation failed', $e->getMessage());
        }
        $this->assertSame($count, Proposition::count());
        $this->assertSame($ledgerCount, DB::table('substrate_ledger_entries')->count());
    }

    public function test_diagnostics_are_read_only_and_leave_all_production_state_unchanged(): void
    {
        $phase = $this->firstCycle->phases()->sole();
        $p = app(ContributionDispatcher::class)->dispatch($this->actor, $phase,
            ['action' => 'proposition.variant', 'statement' => 'A real observed distinction']);
        app(PropositionMeasurementService::class)->recalculate($p);
        $tables = ['proposition_measurements', 'closure_births', 'local_chirality_states',
            'proposition_attestation_cursors', 'commitment_snapshots', 'supersessions', 'participants', 'cycles',
            'substrates', 'phase_profile_observations'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->get()->toJson(), $tables);
        $before = $snapshot();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        app(PhaseProfileService::class)->inspect($p);
        $this->artisan('commons:phase-profile-inspect', ['proposition' => $p->id])->assertSuccessful();
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|alter|create)/i', $sql);
        }
        $this->assertSame($before, $snapshot());
    }
}
