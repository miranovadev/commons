<?php

namespace Tests\Feature;

use App\Domain\Observables\PhaseProfile;
use App\Enums\PhaseKind;
use App\Exceptions\PhaseProfileHistoryUnavailable;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContextHorizonProjector;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\ClosureBirthService;
use App\Services\Observables\PhaseProfileComparisonCalculator;
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

    private ContextHorizon $horizon;

    private Cycle $firstCycle;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = (new Substrate)->forceFill(['slug' => 'phase-profile', 'title' => 'Phase Profile', 'visibility' => 'private', 'configuration' => []]);
        $substrate->save();
        $this->actor = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer']);
        $this->actor->save();
        app(CycleEntryService::class)->enter($this->actor, ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
        $this->origin = $this->actor->fresh()->position;
        $this->horizon = app(ContextHorizonProjector::class)->forEntry($this->origin);
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

    private function born(PhaseKind $kind = PhaseKind::Distinguish, ?Cycle $cycle = null, ?Proposition $gate = null, string $tier = 'expressive'): Proposition
    {
        $cycle ??= $this->firstCycle;
        $gate ??= $this->origin;
        $phase = $cycle->phases()->where('kind', $kind->value)->first();
        if ($phase === null) {
            $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind,
                'gate_proposition_id' => $gate->id, 'write_register' => $cycle->contextHorizon->domain->registerFor($kind), 'started_at' => now()]);
            $phase->save();
        }
        $p = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => $tier,
            'register' => $phase->write_register, 'statement' => 'Structural observation']);
        $p->save();
        app(LedgerService::class)->record($p);
        $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $gate->id,
            'target_proposition_id' => $p->id, 'type' => $tier === 'committal' ? 'commit' : 'variant']);
        $edge->save();
        app(LedgerService::class)->record($edge);

        return $p;
    }

    private function observation(Proposition $p): array
    {
        $row = DB::table('phase_profile_observations')->where('proposition_id', $p->id)->first();
        $this->assertNotNull($row);

        return [$row->developmental_cycle_position === null ? null : (int) $row->developmental_cycle_position,
            $row->cycle_interval === null ? null : (int) $row->cycle_interval];
    }

    private function profile(?int $throughSequence = null): PhaseProfile
    {
        return app(PhaseProfileService::class)->for($this->horizon, $throughSequence);
    }

    public function test_origin_bootstrap_and_empty_child_do_not_share_the_opening_committal(): void
    {
        $this->assertSame([1, 1], $this->observation($this->origin));
        $this->assertSame(PhaseProfile::origin()->values,
            app(PhaseProfileService::class)->for($this->origin->phase->cycle->contextHorizon)->values);
        $this->assertSame(array_fill(0, 7, null), $this->profile()->values);
        $this->born();
        $this->assertSame([1.0, null, null, null, null, null, null], $this->profile()->values);
        $this->assertSame(1.0, (float) $this->origin->commitmentSnapshot->correspondence);
    }

    public function test_intervals_end_at_the_new_proposition_and_phase_means_include_zero(): void
    {
        $p1 = $this->born();
        for ($i = 0; $i < 3; $i++) {
            $this->cycle();
        }
        $p2 = $this->born(PhaseKind::Persist);
        $p3 = $this->born(PhaseKind::Persist);
        $this->cycle();
        $this->cycle();
        $p4 = $this->born(PhaseKind::Mediate);
        $zero = $this->born(PhaseKind::Disperse);
        $this->assertSame([1, 1], $this->observation($p1));
        $this->assertSame([4, 3], $this->observation($p2));
        $this->assertSame([4, 0], $this->observation($p3));
        $this->assertSame([6, 2], $this->observation($p4));
        $this->assertSame([6, 0], $this->observation($zero));
        $this->assertSame([1.0, 1.5, null, 0.0, 2.0, null, null], $this->profile()->values);
    }

    public function test_birth_position_is_immutable_across_later_cycles_replay_and_projection_rebuild(): void
    {
        $p = $this->born();
        $sequence = $p->ledgerEntry->sequence;
        $this->cycle();
        $later = $this->born(PhaseKind::Persist);
        app(LedgerService::class)->record($p);
        app(ContextHorizonProjector::class)->rebuild($this->actor->substrate);
        $this->assertSame([1, 1], $this->observation($p));
        $this->assertSame([2, 1], $this->observation($later));
        $this->assertSame([1.0, null, null, null, null, null, null], $this->profile($sequence)->values);
        $this->assertSame([1.0, 1.0, null, null, null, null, null], $this->profile()->values);
    }

    public function test_cycle_position_counts_horizon_wide_entries_not_participant_sequence(): void
    {
        $other = (new Participant)->forceFill(['substrate_id' => $this->actor->substrate_id, 'callsign' => 'other']);
        $other->save();
        $cycle = $this->cycle(actor: $other);
        $this->assertSame(1, (int) $cycle->sequence);
        $p = $this->born();
        $this->assertSame([2, 2], $this->observation($p));
        $same = $this->born(cycle: $cycle);
        $this->assertSame([2, 0], $this->observation($same));
    }

    public function test_ledger_order_rather_than_proposition_creation_order_selects_previous(): void
    {
        $phase = $this->firstCycle->phases()->sole();
        $savedFirst = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $phase->write_register, 'statement' => 'Ledgered later']);
        $savedFirst->save();
        $ledgeredFirst = $this->born();
        $this->cycle();
        app(LedgerService::class)->record($savedFirst);
        $this->assertSame([1, 1], $this->observation($ledgeredFirst));
        $this->assertSame([2, 1], $this->observation($savedFirst));
    }

    public function test_authored_committal_contributes_order_only_to_its_owning_horizon(): void
    {
        $source = $this->born(PhaseKind::Bias);
        $commit = $this->born(PhaseKind::Order, gate: $source, tier: 'committal');
        $this->assertSame([1, 0], $this->observation($commit));
        $this->assertSame(0.0, $this->profile()->component(PhaseKind::Order));
        $birth = app(ClosureBirthService::class)->stored($commit);
        $snapshot = (new CommitmentSnapshot)->forceFill(['proposition_id' => $commit->id, 'substrate_id' => $this->actor->substrate_id,
            'ledger_entry_id' => $commit->ledgerEntry->id,
            'supersession_id' => Supersession::where('target_proposition_id', $commit->id)->sole()->id,
            'correspondence' => 0, 'chirality' => 0, 'closure' => $birth['closure'], 'cadence_interval' => $birth['cadence_interval'],
            'prominence' => .5, 'turbulence' => .5, 'tension' => 0,
            'direction_before' => $this->horizon->domain, 'direction_after' => $this->horizon->domain->opposite(),
            'calculator_version' => 'phase-profile-fixture', 'evaluated_at' => now()]);
        $snapshot->save();
        $child = app(ContextHorizonProjector::class)->apply($snapshot);
        $this->assertSame(array_fill(0, 7, null), app(PhaseProfileService::class)->for($child)->values);
        $childCycle = $this->cycle($commit);
        $childP = $this->born(cycle: $childCycle, gate: $commit);
        $this->assertSame([1, 1], $this->observation($childP));
        $parentP = $this->born(PhaseKind::Persist);
        $this->assertSame([1, 0], $this->observation($parentP));
        $this->assertSame(0.0, $this->profile()->component(PhaseKind::Order));
    }

    public function test_event_born_sawtooth_matches_the_diagnostic_candidate(): void
    {
        $calculator = app(PhaseProfileComparisonCalculator::class);
        $expected = [-1, -2, -3, -2, -3, -4, -3];
        $this->assertSame(0.0, $calculator->calculate($this->profile(), PhaseProfile::origin()));
        foreach (PhaseKind::cases() as $slot => $kind) {
            if ($slot !== 0 && $kind !== PhaseKind::Disperse) {
                $this->cycle();
            }
            $this->born($kind, tier: $kind === PhaseKind::Order ? 'committal' : 'expressive');
            $this->assertEqualsWithDelta($expected[$slot] / 7,
                $calculator->calculate($this->profile(), PhaseProfile::origin()), 1e-12);
        }
    }

    public function test_missing_historical_observation_is_not_repaired_by_ledger_replay(): void
    {
        $p = $this->born();
        DB::table('phase_profile_observations')->where('proposition_id', $p->id)->delete();
        app(LedgerService::class)->record($p);
        $this->assertDatabaseMissing('phase_profile_observations', ['proposition_id' => $p->id]);
        $next = $this->born(PhaseKind::Persist);
        $this->assertSame([1, null], $this->observation($next));
        $this->expectException(PhaseProfileHistoryUnavailable::class);
        $this->profile();
    }

    public function test_historical_cycle_without_event_order_reports_unavailability(): void
    {
        // Represent pre-cursor storage explicitly; never infer it from timestamps.
        DB::table('cycles')->where('id', $this->firstCycle->id)->update(['chirality_entry_after_sequence' => null]);
        $p = $this->born();
        $this->assertSame([null, null], $this->observation($p));
        $this->expectException(PhaseProfileHistoryUnavailable::class);
        $this->profile();
    }

    public static function immutableColumns(): array
    {
        return [['developmental_cycle_position'], ['cycle_interval']];
    }

    #[DataProvider('immutableColumns')]
    public function test_raw_observation_is_database_immutable(string $column): void
    {
        $p = $this->born();
        $this->expectException(QueryException::class);
        DB::table('phase_profile_observations')->where('proposition_id', $p->id)->update([$column => 99]);
    }

    public function test_observation_failure_rolls_back_the_contribution_transaction(): void
    {
        $before = DB::table('substrate_ledger_entries')->count();
        $this->mock(PhaseProfileObservationService::class, function ($mock): void {
            $mock->shouldReceive('recordBirth')->once()->andThrow(new RuntimeException('Observation failure'));
        });
        try {
            DB::transaction(fn () => $this->born());
            $this->fail('The contribution must roll back.');
        } catch (RuntimeException $error) {
            $this->assertSame('Observation failure', $error->getMessage());
        }
        $this->assertDatabaseCount('propositions', 1);
        $this->assertDatabaseCount('phase_profile_observations', 1);
        $this->assertDatabaseCount('substrate_ledger_entries', $before);
    }

    public function test_diagnostics_are_read_only_and_attestation_history_remains_separate(): void
    {
        $p = $this->born();
        $act = (new Attestation)->forceFill(['phase_id' => $p->phase_id, 'proposition_id' => $p->id, 'direction' => 'support']);
        $act->save();
        app(LedgerService::class)->record($act);
        $measurements = app(PropositionMeasurementService::class);
        $before = $measurements->recalculate($p)->getAttributes();
        $closure = app(ClosureBirthService::class)->stored($p);
        $chirality = DB::table('local_chirality_states')->where('proposition_id', $p->id)->first();
        $cursor = DB::table('proposition_attestation_cursors')->where('proposition_id', $p->id)->first();
        $canonical = $this->origin->commitmentSnapshot->getAttributes();
        $version = $this->actor->substrate->fresh()->field_version;
        $this->assertEqualsWithDelta(-1 / 7, app(PhaseProfileComparisonCalculator::class)->calculate($this->profile(), PhaseProfile::origin()), 1e-12);
        $this->assertSame($before, $p->measurement()->firstOrFail()->getAttributes());
        $this->assertSame($closure, app(ClosureBirthService::class)->stored($p));
        $this->assertEquals($chirality, DB::table('local_chirality_states')->where('proposition_id', $p->id)->first());
        $this->assertEquals($cursor, DB::table('proposition_attestation_cursors')->where('proposition_id', $p->id)->first());
        $this->assertSame($canonical, $this->origin->commitmentSnapshot->fresh()->getAttributes());
        $this->assertSame($version, $this->actor->substrate->fresh()->field_version);
        $this->assertSame([1, 1], $this->observation($p));
    }
}
