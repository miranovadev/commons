<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\LedgerService;
use App\Services\Observables\ClosureBirthService;
use App\Services\Observables\ClosureCalculator;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClosureBirthTest extends TestCase
{
    use RefreshDatabase;

    private Participant $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $s = (new Substrate)->forceFill(['slug' => 'closure-birth', 'title' => 'Closure', 'visibility' => 'private', 'configuration' => []]);
        $s->save();
        $this->actor = (new Participant)->forceFill(['substrate_id' => $s->id, 'callsign' => 'observer']);
        $this->actor->save();
    }

    private function cycle(?Proposition $entry): Cycle
    {
        $h = $entry === null ? app(ContextHorizonProjector::class)->currentFor($this->actor->substrate)
            : app(ContextHorizonProjector::class)->forEntry($entry);
        $cycle = (new Cycle)->forceFill(['participant_id' => $this->actor->id,
            'sequence' => (int) $this->actor->cycles()->max('sequence') + 1,
            'context_horizon_id' => $h->id, 'entry_committal_id' => $entry?->id]);
        $cycle->save();

        return $cycle;
    }

    private function born(?Proposition $entry, string $tier = 'expressive', ?Cycle $cycle = null): Proposition
    {
        $cycle ??= $this->cycle($entry);
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
            'gate_proposition_id' => $entry?->id, 'write_register' => $cycle->contextHorizon->domain->registerFor(PhaseKind::Order), 'started_at' => now()]);
        $phase->save();
        $p = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => $tier,
            'register' => $phase->write_register, 'statement' => 'Birth observation']);
        $p->save();
        app(LedgerService::class)->record($p);
        if ($entry !== null) {
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $entry->id,
                'target_proposition_id' => $p->id, 'type' => $tier === 'committal' ? 'commit' : 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
        }

        return $p;
    }

    private function observation(Proposition $p): array
    {
        return app(ClosureBirthService::class)->stored($p);
    }

    /** Real birth observation, frozen as a committed-history fixture without mocking calculators. */
    private function commit(?Proposition $entry, string $after, int $interval = 1): Proposition
    {
        if ($entry !== null) {
            while (Cycle::where('entry_committal_id', $entry->id)->count() < $interval - 1) {
                $this->cycle($entry);
            }
        }
        $p = $this->born($entry, 'committal');
        $this->freeze($p, $after);

        return $p;
    }

    private function freeze(Proposition $p, string $after): void
    {
        $birth = $this->observation($p);
        $h = $p->phase->cycle->contextHorizon;
        $snapshot = (new CommitmentSnapshot)->forceFill(['proposition_id' => $p->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $p->ledgerEntry->id, 'correspondence' => -1, 'chirality' => -1,
            'closure' => $birth['closure'], 'depth' => $birth['depth'], 'cadence' => $birth['cadence'],
            'cadence_interval' => $birth['cadence_interval'], 'depth_direction' => $birth['depth_direction'], 'cadence_direction' => $birth['cadence_direction'],
            'prominence' => 0, 'turbulence' => 1, 'tension' => -1, 'tension_before' => 0,
            'direction_before' => $h->domain, 'direction_after' => $after, 'calculator_version' => 'birth-fixture', 'evaluated_at' => now()]);
        $snapshot->save();
        app(ContextHorizonProjector::class)->apply($snapshot);
    }

    public function test_origin_and_population_cv_bootstrap_then_full_history_comparison(): void
    {
        $origin = $this->commit(null, 'settlement');
        $o = $this->observation($origin);
        $this->assertSame(1, $o['cadence_interval']);
        $this->assertSame(-1.0, $o['cadence']);
        $this->assertSame('negative', $o['cadence_direction']);
        $s1 = $this->commit($origin, 'emergence', 10);
        $this->assertSame(0.0, $this->observation($s1)['cadence']);
        $e2 = $this->commit($s1, 'settlement', 7);
        $this->assertSame(0.0, $this->observation($e2)['cadence']);
        $this->assertSame('negative', $this->observation($e2)['cadence_direction']);
        $s3 = $this->commit($e2, 'settlement', 12);
        $o = $this->observation($s3);
        $this->assertSame([1, 7], $o['domain_intervals']['emergence']);
        $this->assertSame([10, 12], $o['domain_intervals']['settlement']);
        $this->assertEqualsWithDelta(.75, $o['domain_cvs']['emergence'], 1e-12);
        $this->assertEqualsWithDelta(1 / 11, $o['domain_cvs']['settlement'], 1e-12);
        $this->assertSame(-0.7575758, $o['cadence']);
    }

    public function test_expressive_and_committal_births_observe_different_intervals_and_never_rewrite_source(): void
    {
        $origin = $this->commit(null, 'settlement');
        $s = $this->commit($origin, 'emergence', 10);
        $e = $this->commit($s, 'settlement', 7);
        $cycle = $this->cycle($e);
        $p = $this->born($e, cycle: $cycle);
        $first = $this->observation($p);
        $this->assertSame(1, $first['cadence_interval']);
        $service = app(PropositionMeasurementService::class);
        $service->recalculate($p);
        for ($i = 0; $i < 4; $i++) {
            $this->cycle($e);
        }
        $p2 = $this->born($e, cycle: $cycle);
        $c = $this->born($e, 'committal', $cycle);
        $this->freeze($c, 'settlement');
        $this->assertSame(5, $this->observation($p2)['cadence_interval']);
        $this->assertSame(5, $c->commitmentSnapshot->cadence_interval);
        $this->assertNotSame($first['closure'], $this->observation($c)['closure']);
        $service->recalculate($p);
        $this->assertSame($first, $this->observation($p));
        $this->assertSame($first['closure'], $p->measurement->closure);
        $this->cycle($e); // Later traversal in the old Region cannot extend C's interval.
        $r = $this->born($c);
        $this->assertSame(1, $this->observation($r)['cadence_interval']);
        $this->assertSame(5, $c->commitmentSnapshot->fresh()->cadence_interval);
    }

    public function test_full_domain_depth_counts_do_not_reset_on_domain_switch(): void
    {
        $e0 = $this->commit(null, 'settlement');
        $this->assertSame(['emergence' => 1, 'settlement' => 0], $this->observation($e0)['domain_depths']);
        $s1 = $this->commit($e0, 'emergence');
        $this->assertSame(['emergence' => 1, 'settlement' => 1], $this->observation($s1)['domain_depths']);
        $e2 = $this->commit($s1, 'emergence');
        $this->assertSame(['emergence' => 2, 'settlement' => 1], $this->observation($e2)['domain_depths']);
        $this->assertSame(0.0, $this->observation($e2)['depth']);
        $e3 = $this->born($e2);
        $this->assertSame(['emergence' => 3, 'settlement' => 1], $this->observation($e3)['domain_depths']);
        $this->assertSame(-0.3333333, $this->observation($e3)['depth']);
        $e2b = $this->commit($s1, 'settlement');
        $s3 = $this->born($e2b);
        $this->assertSame(['emergence' => 2, 'settlement' => 2], $this->observation($s3)['domain_depths']);
        $this->assertSame(1.0, $this->observation($s3)['depth']);
    }

    public function test_temporary_slots_do_not_reserve_positions_and_sibling_gaps_remain(): void
    {
        $a = $this->commit(null, 'emergence');
        $b = $this->commit($a, 'emergence');
        $p = $this->born($a);
        $q = $this->born($a);
        $this->assertSame(2, $this->observation($p)['committed_lateral_position']);
        $this->assertSame(2, $this->observation($q)['committed_lateral_position']);
        $c = $this->commit($a, 'emergence');
        $this->assertSame(2, $this->observation($c)['committed_lateral_position']);
        $r = $this->born($a);
        $this->assertSame(3, $this->observation($r)['committed_lateral_position']);
        $leaf = $this->born($c);
        $this->assertNull($this->observation($leaf)['historical_lateral_centroid']); // Opposite Domain has no samples.
        $this->commit($b, 'emergence'); // A left sibling's descendant occupies a row slot.
        $later = $this->born($c);
        $this->assertSame(1, $this->observation($leaf)['committed_lateral_position']);
        $this->assertSame(2, $this->observation($later)['committed_lateral_position']);
        $this->assertSame(2, $this->observation($p)['committed_lateral_position']);
    }

    public function test_full_same_domain_lateral_centroid_uses_frozen_positions_and_keeps_gaps(): void
    {
        $origin = $this->commit(null, 'settlement'); // E coordinate 1
        $s1 = $this->commit($origin, 'settlement');
        $s2 = $this->commit($s1, 'emergence');
        $left = $this->commit($s2, 'emergence');
        $a = $this->commit($s2, 'emergence'); // E coordinate 2
        $fractional = $this->observation($this->born($a));
        $this->assertSame(1.5, $fractional['historical_lateral_centroid']);
        $this->assertSame(1, $fractional['committed_lateral_position']);
        $this->assertSame(.6, $fractional['lateral_committal_position']);
        $left2 = $this->commit($left, 'emergence');
        $b = $this->commit($a, 'emergence'); // 2
        $left3 = $this->commit($left2, 'emergence');
        $this->commit($left2, 'emergence');
        $c = $this->commit($b, 'emergence'); // 3
        $this->commit($left3, 'emergence');
        $aligned = $this->born($c); // 2, against [1,2,2,3]
        $birth = $this->observation($aligned);
        $this->assertEquals([1, 2, 2, 3], array_map(fn ($p) => $this->observation($p)['committed_lateral_position'], [$origin, $a, $b, $c]));
        $this->assertEquals(2.0, $birth['historical_lateral_centroid']);
        $this->assertSame(2, $birth['committed_lateral_position']);
        $this->assertSame(1.0, $birth['lateral_committal_position']);
        $this->commit($left3, 'emergence');
        $farther = $this->observation($this->born($c));
        $this->assertSame(3, $farther['committed_lateral_position']);
        $this->assertLessThan($birth['lateral_committal_position'], $farther['lateral_committal_position']);
        $this->assertSame($birth, $this->observation($aligned));
    }

    public function test_forks_and_measurement_repair_use_the_same_durable_birth_observation(): void
    {
        $a = $this->commit(null, 'settlement');
        $p = $this->born($a);
        $service = app(PropositionMeasurementService::class);
        $service->recalculate($p);
        $before = $this->observation($p);
        $left = $this->commit($a, 'settlement');
        $right = $this->commit($a, 'settlement');
        foreach ([$left, $right] as $branch) {
            $context = new ParticipationContext($branch->phase, collect([$p->id]));
            $this->assertSame($before['closure'], app(ClosureCalculator::class)->components($p, $context)->average());
        }
        DB::table('proposition_measurements')->where('proposition_id', $p->id)->delete();
        $service->recalculate($p);
        $this->assertSame($before['closure'], $p->measurement->closure);
        $this->assertSame($before, $this->observation($p));
    }

    public function test_missing_birth_data_fails_closed_instead_of_using_current_population(): void
    {
        $a = $this->commit(null, 'settlement');
        $p = $this->born($a);
        DB::table('closure_births')->where('proposition_id', $p->id)->delete();
        $this->cycle($a);
        $this->expectException(ClosureHistoryUnavailable::class);
        app(PropositionMeasurementService::class)->recalculate($p);
    }

    public function test_attestation_can_refresh_chirality_while_closure_remains_frozen(): void
    {
        $a = $this->commit(null, 'settlement');
        $p = $this->born($a);
        $service = app(PropositionMeasurementService::class);
        $before = $service->recalculate($p)->chirality;
        $closure = $this->observation($p);
        $act = (new Attestation)->forceFill(['phase_id' => $p->phase_id,
            'proposition_id' => $p->id, 'direction' => 'support']);
        $act->save();
        app(LedgerService::class)->record($act);
        $after = $service->recalculate($p);
        $this->assertNotSame($before, $after->chirality);
        $this->assertSame($closure['closure'], $after->closure);
        $this->assertSame($closure, $this->observation($p));
    }

    public function test_zero_depth_retains_positive_committed_history(): void
    {
        $head = $this->commit(null, 'settlement');
        $head = $this->commit($head, 'settlement');
        $head = $this->commit($head, 'settlement');
        $head = $this->commit($head, 'emergence');
        for ($i = 0; $i < 4; $i++) {
            $head = $this->commit($head, 'emergence');
        }
        $this->assertSame('positive', $this->observation($head)['depth_direction']);
        $zero = $this->observation($this->born($head));
        $this->assertSame(0.0, $zero['depth']);
        $this->assertSame('positive', $zero['depth_direction']);
    }

    public function test_lateral_comparison_requires_two_prior_same_domain_observations(): void
    {
        $origin = $this->commit(null, 'settlement');
        $first = $this->commit($origin, 'settlement');
        $zero = $this->observation($first);
        $this->assertNull($zero['historical_lateral_centroid']);
        $this->assertSame(0.0, $zero['lateral_committal_position']);
        $this->assertSame('negative', $zero['lateral_committal_position_direction']);
        $second = $this->commit($first, 'settlement');
        $one = $this->observation($second);
        $this->assertNull($one['historical_lateral_centroid']);
        $this->assertSame(0.0, $one['lateral_committal_position']);
        $this->assertSame('negative', $one['lateral_committal_position_direction']);
        $immatureNode = $this->born($second);
        $immature = $this->observation($immatureNode);
        $this->assertNull($immature['historical_lateral_centroid']);
        $this->assertSame(0.0, $immature['lateral_committal_position']);
        $this->assertSame('negative', $immature['lateral_committal_position_direction']);
        $this->assertNull($immature['domain_cvs']['emergence']);
        $third = $this->commit($second, 'emergence');
        $e2 = $this->commit($third, 'settlement');
        $current = $this->observation($this->born($e2));
        $this->assertNotNull($current['domain_cvs']['emergence']);
        $this->assertNotNull($current['domain_cvs']['settlement']);
        $this->assertSame($immature, $this->observation($immatureNode));
        $this->assertEquals(1.0, $current['historical_lateral_centroid']);
        $this->assertSame(1.0, $current['lateral_committal_position']);
        $this->assertSame('positive', $current['lateral_committal_position_direction']);
        // Existing fractional-centroid coverage compares current 1 with prior
        // [1, 2] => 1.5, proving the current observation is not a third sample.
    }

    public function test_mature_lateral_does_not_depend_on_cadence_sign_or_force_admissibility(): void
    {
        $origin = $this->commit(null, 'settlement');
        $s1 = $this->commit($origin, 'settlement');
        $s2 = $this->commit($s1, 'emergence');
        $e2 = $this->commit($s2, 'emergence', 2);
        $birth = $this->observation($this->born($e2));
        $this->assertSame([1, 2, 1], $birth['domain_intervals']['emergence']);
        $this->assertSame([1, 1], $birth['domain_intervals']['settlement']);
        $this->assertSame(-1.0, $birth['cadence']);
        $this->assertSame(1.0, $birth['lateral_committal_position']);
        $this->assertSame('positive', $birth['committed_cardinal_position_direction']);
        $this->assertSame('negative', $birth['cadence_direction']);
        $this->assertFalse($birth['admissible']);
    }

    public static function secondCommitDomains(): array
    {
        return [['settlement'], ['emergence']];
    }

    #[DataProvider('secondCommitDomains')]
    public function test_unbranched_second_commit_retains_aligned_bootstrap_directions(string $domain): void
    {
        $origin = $this->commit(null, 'settlement');
        $first = $this->commit($origin, $domain);
        $candidate = new Proposition;
        $candidate->forceFill(['tier' => 'committal']);
        $phase = new Phase;
        $phase->setRelation('cycle', $this->cycle($first));
        $candidate->setRelation('phase', $phase);
        $births = app(ClosureBirthService::class);
        foreach ([1, 5] as $population) {
            while (Cycle::where('entry_committal_id', $first->id)->count() < $population) {
                $this->cycle($first);
            }
            $observation = $births->prospective($candidate);
            $this->assertSame(0.0, $observation['depth']);
            $this->assertSame(0.0, $observation['lateral_committal_position']);
            $this->assertSame(0.0, $observation['committed_cardinal_position']);
            $this->assertSame('negative', $observation['committed_cardinal_position_direction']);
            $this->assertSame(0.0, $observation['cadence']);
            $this->assertSame('negative', $observation['cadence_direction']);
            $this->assertTrue($observation['admissible']);
        }
        $this->assertDatabaseCount('closure_births', 2);
    }

    public function test_birth_observation_is_database_immutable(): void
    {
        $p = $this->born(null, 'committal');
        $this->expectException(QueryException::class);
        DB::table('closure_births')->where('proposition_id', $p->id)->update(['observation' => '{}']);
    }
}
