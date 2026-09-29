<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ClosureGateService;
use App\Services\ContextHorizonProjector;
use App\Services\LedgerService;
use App\Services\Observables\ClosureCalculator;
use App\Services\Observables\CommittalLineageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommittalClosureTest extends TestCase
{
    use RefreshDatabase;

    private Participant $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $s = (new Substrate)->forceFill(['slug' => 'committed-closure', 'title' => 'Closure', 'visibility' => 'private', 'configuration' => []]);
        $s->save();
        $this->actor = (new Participant)->forceFill(['substrate_id' => $s->id, 'callsign' => 'observer']);
        $this->actor->save();
    }

    private function candidate(?Proposition $governor): Proposition
    {
        $horizon = $governor === null ? app(ContextHorizonProjector::class)->currentFor($this->actor->substrate)
            : app(ContextHorizonProjector::class)->forEntry($governor);
        $c = (new Cycle)->forceFill(['participant_id' => $this->actor->id,
            'sequence' => (int) $this->actor->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id, 'entry_committal_id' => $governor?->id]);
        $c->save();
        $ph = (new Phase)->forceFill(['cycle_id' => $c->id, 'kind' => PhaseKind::Order,
            'write_register' => $horizon->domain->registerFor(PhaseKind::Order), 'started_at' => now()]);
        $ph->save();
        $p = (new Proposition)->forceFill(['phase_id' => $ph->id, 'tier' => 'committal',
            'register' => $ph->write_register, 'statement' => 'Committed lineage fixture']);
        $p->save();
        app(LedgerService::class)->record($p);

        return $p;
    }

    private function freeze(Proposition $p, ?int $interval, string $after, float $depth = 0, float $cadence = 0): Proposition
    {
        $h = $p->phase->cycle->contextHorizon;
        $s = (new CommitmentSnapshot)->forceFill(['proposition_id' => $p->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $p->ledgerEntry->id, 'correspondence' => -1, 'chirality' => -1,
            'closure' => ($depth + $cadence) / 2, 'depth' => $depth, 'cadence' => $cadence,
            'cadence_interval' => $interval, 'depth_direction' => $depth > 0 ? 'positive' : 'negative',
            'cadence_direction' => $cadence > 0 ? 'positive' : 'negative',
            'prominence' => 0, 'turbulence' => 1, 'tension' => -1, 'tension_before' => 0,
            'direction_before' => $h->domain, 'direction_after' => $after,
            'calculator_version' => $interval === null && $p->phase->cycle->entry_committal_id === null ? 'origin-bootstrap-v1' : 'fixture',
            'evaluated_at' => now()]);
        $s->save();
        app(ContextHorizonProjector::class)->apply($s);
        $p->phase->cycle->forceFill(['completed_at' => now()])->save();

        return $p;
    }

    private function origin(): Proposition
    {
        return $this->freeze($this->candidate(null), null, 'settlement', -1, -1);
    }

    private function completed(Proposition $governor, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $p = $this->candidate($governor);
            $p->phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        }
    }

    public function test_symmetric_bootstrap_and_domain_runs_follow_only_cycle_entry(): void
    {
        $calc = app(ClosureCalculator::class);
        $e0 = $this->origin();
        $s1 = $this->candidate($e0);
        $zero = $calc->components($s1)->resolve();
        $this->assertSame(0.0, $zero['depth']);
        $this->assertSame(0.0, $zero['cadence']);
        $this->assertSame('negative', $zero['depth_direction']);
        $this->assertSame('negative', $zero['cadence_direction']);
        $this->assertTrue($zero['admissible']);
        $this->freeze($s1, 1, 'settlement');
        $s2 = $this->candidate($s1);
        $this->assertSame(0.0, $calc->components($s2)->depth);
        $this->freeze($s2, 1, 'settlement');
        $s3 = $this->candidate($s2);
        $this->assertSame(-0.3333333, $calc->components($s3)->depth);
        $this->freeze($s3, 1, 'emergence');
        $e1 = $this->candidate($s3);
        $this->assertSame(-0.3333333, $calc->components($e1)->depth);
        $this->freeze($e1, 1, 'emergence');
        $e2 = $this->candidate($e1);
        $this->assertSame(0.3333333, $calc->components($e2)->depth);
        $this->assertSame(1.0, $calc->components($e2)->cadence);
    }

    public function test_similarity_and_interval_include_every_entry_once_but_exclude_other_basins(): void
    {
        $this->assertSame(1.0, ClosureCalculator::similarity(10, 10));
        $this->assertSame(0.3333333, ClosureCalculator::similarity(10, 15));
        $this->assertSame(0.0, ClosureCalculator::similarity(10, 20));
        $this->assertSame(-0.5, ClosureCalculator::similarity(10, 40));
        $a = $this->origin();
        $this->completed($a, 3);
        $open = $this->candidate($a);
        $b = $this->candidate($a);
        $lineage = app(CommittalLineageService::class);
        $this->assertSame(5, $lineage->interval($b));
        $this->freeze($b, 5, 'settlement');
        $this->assertSame(5, $lineage->interval($b));
        $this->completed($b, 5);
        $open->phase->cycle->forceFill(['completed_at' => now()])->save();
        $b2 = $this->candidate($a);
        $this->assertSame(6, $lineage->interval($b2));
        $this->assertSame(5, $b->commitmentSnapshot->cadence_interval);
    }

    public function test_expressive_closure_is_prospective_and_entry_changes_its_live_interval_without_writes(): void
    {
        $origin = $this->origin();
        $candidate = $this->candidate($origin);
        $candidate->tier = 'expressive';
        $before = DB::table('commitment_snapshots')->get()->toJson();
        $calculator = app(ClosureCalculator::class);
        $lineage = app(CommittalLineageService::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $values = $calculator->components($candidate);
        $this->assertSame(0.0, $values->depth);
        $this->assertSame(0.0, $values->cadence);
        $this->assertSame(1, $lineage->interval($candidate));
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^\s*(insert|update|delete)/i', $query['query']));
        DB::disableQueryLog();
        $this->assertCount(0, $writes);
        $other = $this->candidate($origin);
        $this->assertNull($other->phase->cycle->completed_at);
        $this->assertSame(2, $lineage->interval($candidate));
        $other->phase->cycle->forceFill(['completed_at' => now()])->save();
        $this->assertSame(2, $lineage->interval($candidate));
        $this->assertSame($before, DB::table('commitment_snapshots')->get()->toJson());
    }

    public function test_abandoned_descendants_do_not_supply_structure_intervals_or_directions(): void
    {
        $c0 = $this->origin();
        $c1 = $this->freeze($this->candidate($c0), 10, 'settlement');
        $c2a = $this->freeze($this->candidate($c1), 99, 'settlement', 1, 1);
        $this->freeze($this->candidate($c2a), 300, 'settlement', 1, 1);
        $this->completed($c2a, 4);
        $c2b = $this->candidate($c1);
        $lineage = app(CommittalLineageService::class);
        $this->assertSame([$c1->id, $c0->id], $lineage->ancestors($c2b)->pluck('proposition_id')->all());
        $this->assertSame($c1->id, app(ClosureGateService::class)->historyFor($c2b)->proposition_id);
        $values = app(ClosureCalculator::class)->components($c2b);
        $this->assertSame(0.0, $values->depth);
        $this->assertSame(-0.6, $values->cadence); // Two Cycles in C1, compared with its frozen ten.
        $this->assertSame('negative', $values->resolve()['depth_direction']);
    }

    public function test_ambiguous_legacy_interval_is_explicitly_unavailable_without_rewriting_observables(): void
    {
        $a = $this->origin();
        $b = $this->freeze($this->candidate($a), null, 'settlement');
        $c = $this->candidate($b);
        $before = $b->commitmentSnapshot->getAttributes();
        $preview = app(ClosureGateService::class)->preview($b, $c->phase);
        $this->assertFalse($preview['admissible']);
        $this->assertSame('cadence_history_unavailable', $preview['unavailable_reason']);
        $this->assertNull($preview['cadence']);
        $this->assertSame($before, $b->commitmentSnapshot->fresh()->getAttributes());
        $c->tier = 'expressive'; // In-memory observation only; no historical rewrite.
        $this->expectException(ClosureHistoryUnavailable::class);
        app(ClosureCalculator::class)->components($c);
    }

    public function test_migration_backfills_only_proven_intervals_and_leaves_entry_and_observables_unchanged(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 00:00:00'));
        $a = $this->origin();
        $this->completed($a, 3);
        $this->travel(1)->seconds();
        $b = $this->freeze($this->candidate($a), 4, 'settlement');
        $this->travel(1)->seconds();
        $b2 = $this->freeze($this->candidate($a), 5, 'settlement');
        $this->completed($a, 1); // Same-second other-Cycle completion makes B2 ambiguous.
        $before = DB::table('commitment_snapshots')->orderBy('proposition_id')->get()
            ->map(fn ($row) => Arr::except((array) $row, ['cadence_interval']))->all();
        $governors = Cycle::orderBy('id')->pluck('entry_committal_id', 'id')->all();
        $rename = require database_path('migrations/2026_09_22_210000_rename_cycle_governance_to_entry.php');
        $rename->down();
        $migration = require database_path('migrations/2026_09_22_120000_freeze_committal_cadence_intervals.php');
        $migration->down();
        DB::table('potential_dirty_horizons')->delete();
        $version = (int) $this->actor->substrate->fresh()->field_version;
        $migration->up();
        $rename->up();
        $this->assertSame($version + 1, (int) $this->actor->substrate->fresh()->field_version);
        $this->assertEqualsCanonicalizing(DB::table('context_horizons')->pluck('id')->all(),
            DB::table('potential_dirty_horizons')->where('measurements_dirty', true)->pluck('context_horizon_id')->all());
        $this->assertSame(4, $b->commitmentSnapshot->fresh()->cadence_interval);
        $this->assertNull($b2->commitmentSnapshot->fresh()->cadence_interval);
        $this->assertNull($a->commitmentSnapshot->fresh()->cadence_interval);
        $this->assertSame($governors, Cycle::orderBy('id')->pluck('entry_committal_id', 'id')->all());
        $this->assertSame($before, DB::table('commitment_snapshots')->orderBy('proposition_id')->get()
            ->map(fn ($row) => Arr::except((array) $row, ['cadence_interval']))->all());
        $rename->down();
        $migration->down();
        $this->assertSame($before, DB::table('commitment_snapshots')->orderBy('proposition_id')->get()->map(fn ($row) => (array) $row)->all());
        $migration->up();
        $rename->up();
    }

    public function test_entry_reads_are_bounded_as_committed_lineage_grows(): void
    {
        $governor = $this->origin();
        $counts = [];
        for ($i = 1; $i <= 12; $i++) {
            $candidate = $this->candidate($governor);
            $candidate->load('phase.cycle.participant', 'phase.cycle.contextHorizon');
            if (in_array($i, [2, 12], true)) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                app(ClosureCalculator::class)->components($candidate);
                $counts[] = count(DB::getQueryLog());
                DB::disableQueryLog();
            }
            $governor = $this->freeze($candidate, 1, 'settlement');
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(3, $counts[1]);
    }

    public function test_completion_versions_the_field_and_frozen_interval_cannot_be_updated(): void
    {
        $a = $this->origin();
        $b = $this->candidate($a);
        $before = (int) $this->actor->substrate->fresh()->field_version;
        $this->freeze($b, 1, 'settlement');
        $this->assertGreaterThan($before, (int) $this->actor->substrate->fresh()->field_version);
        $this->expectException(QueryException::class);
        DB::table('commitment_snapshots')->where('proposition_id', $b->id)->update(['cadence_interval' => 2]);
    }
}
