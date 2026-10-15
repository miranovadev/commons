<?php

namespace Tests\Feature;

use App\Domain\Observables\ObservableValues;
use App\Enums\ContextDomain;
use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Models\Supersession;
use App\Services\AttestationEvidenceService;
use App\Services\ClosureGateService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\CommitRegionPolarityService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\ProjectionService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\AssertsLatePhaseContract;
use Tests\Fixtures\PropositionBirth;
use Tests\TestCase;

/** Service contracts with explicit structural fixtures; not a full seven-Phase lifecycle. */
class ClosureHysteresisTest extends TestCase
{
    use AssertsLatePhaseContract;
    use RefreshDatabase;

    private Participant $participant;

    private Proposition $origin;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('schemas.cache.store', 'array');
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'closure', 'title' => 'Closure', 'visibility' => 'private', 'configuration' => []])->save();
        $this->participant = new Participant;
        $this->participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer', 'max_read_count' => 25])->save();
    }

    public function test_read_only_preview_preserves_source_measurements_and_the_authorised_closure_state(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        $counts = $this->counts();
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $this->publishPotentialFixture($this->participant);
        $state = app(ParticipationService::class)->stateFor($this->participant);
        $this->assertSame($counts, $this->counts());
        $this->assertSame(0.0, $preview['depth']);
        $this->assertSame(0.0, $preview['cadence']);
        $this->assertSame('ready', $state['status']);
        $this->assertTrue($state['plan']['commitment']['closure_enforced']);

        $resolved = app(PropositionMeasurementService::class)->recalculate($gate);
        $this->publishPotentialFixture($this->participant);
        $result = app(ParticipationService::class)->contribute($this->participant, $phase->id, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
        $snapshot = CommitmentSnapshot::findOrFail($result['current_proposition_id']);
        $this->assertSame($preview['depth'], $snapshot->depth);
        $this->assertSame($preview['cadence'], $snapshot->cadence);
        $this->assertSame($preview['cadence_interval'], $snapshot->cadence_interval);
        $this->assertSame($preview['closure'], $snapshot->closure);
        $this->assertSame($preview['depth_direction'], $snapshot->depth_direction->value);
        $this->assertSame($preview['history_proposition_id'], $snapshot->closure_history_proposition_id);
        $this->assertSame('settlement', $result['last_commitment']['domain_before']);
        foreach (['correspondence', 'chirality', 'closure'] as $component) {
            $this->assertSame($resolved->$component, $snapshot->$component);
        }
        $this->assertEquals($preview, $snapshot->closure_observation);
        // Preserve the existing two-stage Prominence/Turbulence rounding.
        $field = app(ProjectionService::class)->field(new ObservableValues($resolved->correspondence, $resolved->chirality, $preview['closure']));
        $this->assertSame($field['tension'], $snapshot->tension);
        $this->assertSame(ContextDomain::after($snapshot->tension, ContextDomain::Settlement)->value, $result['last_commitment']['domain_after']);
        $this->assertSame(-1.0, $result['last_commitment']['tension_before']);
        $this->assertSame($snapshot->direction_before !== $snapshot->direction_after, $result['last_commitment']['closure_boundary']);
        $this->assertSame(2, $phase->cycle->contextHorizon->sequence);
        $this->assertSame($snapshot->proposition_id, $this->participant->fresh()->current_proposition_id);
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->publishPotentialFixture($this->participant);
        $this->assertSame($result['last_commitment'], app(ParticipationService::class)->stateFor($this->participant)['last_commitment']);
    }

    public function test_an_observed_commit_retries_after_cycle_entry_even_when_bootstrap_remains_admissible(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        $this->publishPotentialFixture($this->participant);
        $observed = app(ParticipationService::class)->stateFor($this->participant)['plan']['schema'];
        $other = (new Participant)->forceFill(['substrate_id' => $this->participant->substrate_id, 'callsign' => 'concurrent']);
        $other->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $other->id, 'sequence' => 1,
            'context_horizon_id' => $phase->cycle->context_horizon_id, 'entry_committal_id' => $this->origin->id]);
        $cycle->save();
        $this->assertNull($cycle->completed_at);
        $this->publishPotentialFixture($this->participant);
        $this->assertTrue(app(ClosureGateService::class)->preview($gate, $phase)['admissible']);
        $before = CommitmentSnapshot::count();
        try {
            app(ParticipationService::class)->contribute($this->participant, $phase->id,
                ['action' => 'proposition.commit', 'proposition_id' => $gate->id], $observed);
            $this->fail('A changed observed interval must retry even if its direction still aligns.');
        } catch (ConflictHttpException $error) {
            $this->assertStringContainsString('Closure changed', $error->getMessage());
        }
        $this->assertSame($before, CommitmentSnapshot::count());
        $this->assertNull($phase->fresh()->completed_at);
    }

    public function test_source_attestation_cannot_bypass_opposed_extent_and_rhythm(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2], direction: 'refute');
        $this->assertSame(ObservableDirection::Negative, app(AttestationEvidenceService::class)->directionFor($gate));
        $eligibility = app(CommitmentAdmissibilityService::class)->evaluate($gate, $phase);
        $this->assertTrue($eligibility['requirements']['attestation']);
        $this->assertTrue($eligibility['requirements']['receipt']);
        $this->assertSame('positive', $eligibility['closure']['historical_extent_direction']);
        $this->assertSame('negative', $eligibility['closure']['historical_rhythm_direction']);
        $this->assertFalse($eligibility['admissible']);
        $counts = $this->counts();
        try {
            app(ContributionDispatcher::class)->dispatch($this->participant, $phase,
                ['action' => 'proposition.commit', 'proposition_id' => $gate->id]);
            $this->fail('Grounding must not bypass independent Closure coherence.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('extent_rhythm_directions_diverge', $exception->getMessage());
        }
        $this->assertSame($counts, $this->counts());
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertSame($gate->id, $this->participant->fresh()->current_proposition_id);
        $this->assertSame(['governing' => ObservableDirection::Positive, 'support' => ObservableDirection::Positive],
            app(CommitRegionPolarityService::class)->forSeed($this->origin));
    }

    public function test_coherent_closure_preserves_source_without_prescribing_the_next_domain(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2, 3, 6]);
        for ($i = 0; $i < 8; $i++) {
            $touch = new Phase;
            $touch->forceFill(['cycle_id' => $phase->cycle_id, 'kind' => PhaseKind::Persist,
                'write_register' => 'stance', 'gate_proposition_id' => $gate->id,
                'started_at' => now(), 'completed_at' => now()])->save();
        }
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $this->assertSame(0.0, $preview['depth']);
        $this->assertSame(0.0, $preview['cadence']);
        $this->assertSame('negative', $preview['depth_direction']);
        $this->assertSame('negative', $preview['cadence_direction']);
        $this->assertTrue($preview['admissible']);
        $this->assertSame($preview['historical_extent_direction'], $preview['historical_rhythm_direction']);
        $resolved = app(PropositionMeasurementService::class)->recalculate($gate);
        $this->publishPotentialFixture($this->participant);
        $state = app(ParticipationService::class)->contribute($this->participant, $phase->id, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
        $snapshot = CommitmentSnapshot::findOrFail($state['current_proposition_id']);
        foreach (['correspondence', 'chirality', 'closure'] as $component) {
            $this->assertSame($resolved->$component, $snapshot->$component);
        }
        $this->assertEquals($preview, $snapshot->closure_observation);
        // Preserve the existing two-stage Prominence/Turbulence rounding.
        $field = app(ProjectionService::class)->field(new ObservableValues($resolved->correspondence, $resolved->chirality, $preview['closure']));
        $this->assertSame($field['tension'], $snapshot->tension);
        $expectedDomain = ContextDomain::after($snapshot->tension, ContextDomain::Settlement);
        $this->assertSame($expectedDomain->value, $state['domain']);
        $this->assertSame($expectedDomain !== ContextDomain::Settlement, $state['last_commitment']['closure_boundary']);
        $this->assertSame($preview['depth'], $snapshot->depth);
        $this->assertSame($preview['cadence'], $snapshot->cadence);
        $this->assertSame($preview['cadence_interval'], $snapshot->cadence_interval);
        $this->assertSame($preview['closure'], $snapshot->closure);
    }

    public function test_first_post_origin_commit_has_symmetric_unresolved_components(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        $this->assertSame(1, CommitmentSnapshot::count());
        $this->assertSame(ContextDomain::Settlement, ContextHorizon::whereNull('closed_at')->firstOrFail()->domain);
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $this->assertSame(0.0, $preview['depth']);
        $this->assertSame(0.0, $preview['cadence']);
        $this->assertTrue($preview['admissible']);
        $this->assertSame(1, CommitmentSnapshot::count());
    }

    public function test_expressive_depth_and_phase_touches_do_not_change_committed_bootstrap(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2, 3, 4]);
        $before = app(ClosureGateService::class)->preview($gate, $phase);
        for ($i = 0; $i < 9; $i++) {
            $touch = new Phase;
            $touch->forceFill(['cycle_id' => $phase->cycle_id, 'kind' => PhaseKind::Persist,
                'write_register' => 'stance', 'gate_proposition_id' => $gate->id,
                'started_at' => now(), 'completed_at' => now()])->save();
        }
        $after = app(ClosureGateService::class)->preview($gate, $phase);
        $this->assertSame($before, $after);
        $this->assertSame(0.0, $after['depth']);
        $this->assertSame(0.0, $after['cadence']);
    }

    public function test_zero_extent_retains_negative_history_and_blocks_a_positive_rhythm(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        $this->previewClosureFixture($gate, -1, 1);
        $this->previewClosureFixture($gate, 0, 1);
        $counts = $this->counts();
        $this->publishPotentialFixture($this->participant);
        $state = app(ParticipationService::class)->stateFor($this->participant);
        $closure = $state['plan']['commitment']['closure'];
        $this->assertSame(0.0, $closure['historical_extent']);
        $this->assertSame('negative', $closure['historical_extent_direction']);
        $this->assertSame('positive', $closure['historical_rhythm_direction']);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertContains('extent_rhythm_directions_diverge', $state['plan']['commitment']['reasons']);
        $this->assertArrayNotHasKey('proposition.commit', $state['plan']['schema']['$defs']['actions']);
        $this->assertSame(['participant.rest'], array_keys($state['plan']['schema']['$defs']['actions']));
        try {
            app(ContributionDispatcher::class)->dispatch($this->participant, $phase, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
            $this->fail('Direct dispatch must not bypass Closure.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('extent_rhythm_directions_diverge', $exception->getMessage());
        }
        $this->assertSame($counts, $this->counts());
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertSame($gate->id, $this->participant->fresh()->current_proposition_id);
    }

    public function test_unrelated_newer_snapshot_does_not_supply_lineage_history(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        [$siblingPhase, $siblingSource] = $this->candidate([3]);
        $this->publishPotentialFixture($this->participant);
        $result = app(ParticipationService::class)->contribute($this->participant, $siblingPhase->id,
            ['action' => 'proposition.commit', 'proposition_id' => $siblingSource->id]);
        $sibling = Proposition::findOrFail($result['current_proposition_id']);
        $before = app(ProspectiveClosureService::class)->stored($gate);
        $service = app(ClosureGateService::class);
        $this->assertSame($this->origin->id, $service->historyFor($gate)->proposition_id);
        $this->assertSame($before, $service->preview($gate, $phase));

        $merged = $this->node($phase, 'expressive', 'concord', $gate);
        $this->edge($phase, $gate, $merged, 'reconcile');
        $this->edge($phase, $sibling, $merged, 'reconcile');
        PropositionBirth::complete($merged);
        $this->assertSame($this->origin->id, $service->historyFor($merged)->proposition_id);
    }

    public function test_actual_candidate_rejection_rolls_back_a_previously_admissible_preview(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $counts = $this->counts();
        $this->mock(ClosureGateService::class, function ($mock) use ($preview): void {
            $mock->shouldReceive('preview')->andReturn($preview);
            $mock->shouldReceive('forCommitment')->once()->andReturn([
                ...$preview, 'admissible' => false,
            ]);
        });

        try {
            app(ContributionDispatcher::class)->dispatch($this->participant, $phase, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
            $this->fail('An actual candidate rejection must roll back the write.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Historical Extent/Rhythm', $exception->getMessage());
            $this->assertTrue($preview['admissible']);
        }
        $this->assertSame($counts, $this->counts());
        $this->assertSame($gate->id, $this->participant->fresh()->current_proposition_id);
        $this->assertNull($phase->fresh()->completed_at);
        $this->publishPotentialFixture($this->participant);
        $this->assertSame('settlement', app(ParticipationService::class)->stateFor($this->participant)['domain']);
    }

    public function test_unavailable_authorised_state_fails_closed_without_rewriting_history(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        $snapshot = $this->origin->commitmentSnapshot;
        $before = $snapshot->getAttributes();
        DB::table('prospective_closure_states')->where('proposition_id', $gate->id)->delete();
        $counts = $this->counts();
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $this->assertFalse($preview['admissible']);
        $this->assertNull($preview['historical_extent']);
        $this->assertNull($preview['historical_rhythm']);
        $this->assertSame('prospective_closure_unavailable', $preview['unavailable_reason']);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
        $this->assertSame($counts, $this->counts());
        $this->assertDatabaseMissing('prospective_closure_states', ['proposition_id' => $gate->id]);
    }

    public static function latePhaseDirections(): array
    {
        return [[.5, .5, true], [-.5, -.5, true], [.5, -.5, false], [-.5, .5, false]];
    }

    #[Group('late-phase')]
    #[DataProvider('latePhaseDirections')]
    public function test_order_native_preview_explains_visible_write_actionability(float $extent, float $rhythm, bool $allowed): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        // Controlled native components; real Closure resolution, planning and serialization.
        $this->previewClosureFixture($gate, $extent, $rhythm);
        $this->publishPotentialFixture($this->participant);
        $plan = app(InputPlanService::class)->forPhase($this->participant, $phase);
        $this->assertLatePhaseContract($plan, PhaseKind::Order, $gate->id, $allowed,
            $extent > 0 ? 'positive' : 'negative', $rhythm > 0 ? 'positive' : 'negative');
        if (! $allowed) {
            foreach ([0.0, .75] as $potential) {
                DB::table('potential_region_states')->where('context_horizon_id', $phase->cycle->context_horizon_id)
                    ->update(['remaining_potential' => $potential]);
                $plan = app(InputPlanService::class)->forPhase($this->participant, $phase);
                $this->assertSame($potential, app(PotentialProjectionService::class)->region($phase->cycle->context_horizon_id)['potential']);
                $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
            }
        }
    }

    #[Group('late-phase')]
    public function test_admissible_preview_cannot_authorize_commit_after_closure_becomes_opposed(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2]);
        $observed = app(ParticipationService::class)->stateFor($this->participant)['plan']['schema'];
        $this->assertTrue(app(ClosureGateService::class)->preview($gate, $phase)['admissible']);
        $this->previewClosureFixture($gate, -1, 1);
        $this->previewClosureFixture($gate, 0, 1); // Controlled changed state: retained negative Extent, positive Rhythm.
        $this->assertFalse(app(ClosureGateService::class)->preview($gate, $phase)['admissible']);
        $this->publishPotentialFixture($this->participant);
        $before = $this->counts();
        try {
            app(ParticipationService::class)->contribute($this->participant, $phase->id,
                ['action' => 'proposition.commit', 'proposition_id' => $gate->id], $observed);
            $this->fail('An earlier admissible observation cannot authorize a now-blocked Commit.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('Closure changed', $exception->getMessage());
        }
        $this->assertSame($before, $this->counts());
        $this->assertSame($gate->id, $this->participant->fresh()->current_proposition_id);
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertNull($phase->cycle->fresh()->completed_at);
    }

    /** Numerical contract isolation only: retain real birth history and production read/Commit guards. */
    private function previewClosureFixture(Proposition $gate, float $extent, float $rhythm): void
    {
        $state = app(ProspectiveClosureService::class)->stored($gate);
        $extentDirection = ObservableDirection::after($extent, ObservableDirection::from($state['historical_extent_direction']));
        $rhythmDirection = ObservableDirection::after($rhythm, ObservableDirection::from($state['historical_rhythm_direction']));
        $state = [...$state, 'historical_extent' => $extent, 'historical_rhythm' => $rhythm,
            'historical_extent_direction' => $extentDirection->value,
            'historical_rhythm_direction' => $rhythmDirection->value,
            'closure' => ($extent + $rhythm) / 2, 'admissible' => $extentDirection === $rhythmDirection];
        $this->assertSame(1, DB::table('prospective_closure_states')->where('proposition_id', $gate->id)
            ->where('frozen', false)->update(['observation' => json_encode($state, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)]));
    }

    private function bootstrap(): void
    {
        $phase = $this->phase(1, 'canon');
        // Origin is the unique parentless Committal; no expressive pre-Origin history.
        $this->origin = $this->node($phase, 'committal', 'canon');
        app(PropositionMeasurementService::class)->recalculate($this->origin);
        // Pre-upgrade immutable history is intentionally not retroactively reinforced.
        $snapshot = new CommitmentSnapshot;
        $horizon = $this->origin->contextHorizonMembership->horizon;
        $snapshot->forceFill(['proposition_id' => $this->origin->id, 'substrate_id' => $this->participant->substrate_id,
            'ledger_entry_id' => $this->origin->ledgerEntry->id, 'correspondence' => -1, 'chirality' => -1, 'closure' => -1,
            'depth' => -1, 'cadence' => -1, 'depth_direction' => 'negative', 'cadence_direction' => 'negative',
            'prominence' => 0, 'turbulence' => 1, 'tension' => -1, 'tension_before' => 0,
            'direction_before' => 'emergence', 'direction_after' => 'settlement',
            'calculator_version' => 'fixture-bootstrap', 'evaluated_at' => now()])->save();
        app(ContextHorizonProjector::class)->apply($snapshot);
        $this->assertSame(ObservableDirection::Negative, $snapshot->depth_direction);
        $this->assertSame(ObservableDirection::Negative, $snapshot->cadence_direction);
        $this->finish($phase);
    }

    /** @param list<int> $cycles @return array{Phase, Proposition} */
    private function candidate(array $cycles, string $writeRegister = 'concord', string $gateRegister = 'signal', string $direction = 'support'): array
    {
        $previous = $this->origin;
        foreach ($cycles as $sequence) {
            $phase = $this->phase($sequence, $writeRegister);
            $gate = $this->node($this->phaseAtPositionFixture($phase, PhaseKind::Bias), 'expressive', $sequence === end($cycles) ? $writeRegister : $gateRegister, $previous);
            $this->edge($phase, $previous, $gate, 'variant');
            PropositionBirth::complete($gate);
            $phase->gate_proposition_id = $gate->id;
            $phase->save();
            if ($sequence !== end($cycles)) {
                $this->finish($phase);
            }
            $previous = $gate;
        }
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $phase->id, 'proposition_id' => $gate->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($attestation);
        $this->loadedReceiptFixture($gate);
        $this->participant->current_proposition_id = $gate->id;
        $this->participant->save();

        $this->publishPotentialFixture($this->participant);

        return [$phase, $gate];
    }

    private function phase(int $sequence, string $register): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->participant->id, 'sequence' => $sequence,
            'entry_committal_id' => isset($this->origin) ? $this->origin->id : null])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order, 'write_register' => $register, 'started_at' => now()])->save();

        return $phase;
    }

    private function node(Phase $phase, string $tier, string $register, ?Proposition $source = null): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => $tier, 'register' => $register, 'statement' => 'Fixture proposition.'])->save();
        app(LedgerService::class)->record($node, $source);

        return $node;
    }

    private function edge(Phase $phase, Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $source->id, 'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    private function finish(Phase $phase): void
    {
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();
    }

    /** @return list<int> */
    private function counts(): array
    {
        return [Proposition::count(), Supersession::count(), CommitmentSnapshot::count(), ContextHorizon::count(), SubstrateLedgerEntry::count()];
    }
}
