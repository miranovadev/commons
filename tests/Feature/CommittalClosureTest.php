<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'context_horizon_id' => $horizon->id, 'governing_committal_id' => $governor?->id]);
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
            'calculator_version' => $interval === null && $p->phase->cycle->governing_committal_id === null ? 'origin-bootstrap-v1' : 'fixture',
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

    public function test_symmetric_bootstrap_and_domain_runs_follow_only_governance(): void
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

    public function test_similarity_and_interval_include_rest_and_producer_but_exclude_open_and_other_basins(): void
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
        $this->assertSame(4, $lineage->interval($b));
        $this->freeze($b, 4, 'settlement');
        $this->assertSame(4, $lineage->interval($b));
        $this->completed($b, 5);
        $open->phase->cycle->forceFill(['completed_at' => now()])->save();
        $b2 = $this->candidate($a);
        $this->assertSame(6, $lineage->interval($b2));
        $this->assertSame(4, $b->commitmentSnapshot->cadence_interval);
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

    public function test_completion_versions_the_field_and_frozen_interval_cannot_be_updated(): void
    {
        $a = $this->origin();
        $b = $this->candidate($a);
        $before = (int) $this->actor->substrate->fresh()->field_version;
        $this->freeze($b, 1, 'settlement');
        $this->assertGreaterThan($before, (int) $this->actor->substrate->fresh()->field_version);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('commitment_snapshots')->where('proposition_id', $b->id)->update(['cadence_interval' => 2]);
    }
}
