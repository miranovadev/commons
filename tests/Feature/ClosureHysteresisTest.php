<?php

namespace Tests\Feature;

use App\Domain\Observables\LocalMeasurements;
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
use App\Services\CommitRegionPolarityService;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\ParticipationService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosureHysteresisTest extends TestCase
{
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

    public function test_read_only_bootstrap_preview_matches_actual_commit_while_field_remains_in_settlement(): void
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
        // The corrected comparison lowers Correspondence; Closure is unchanged.
        $this->assertSame(-0.0964505, $snapshot->correspondence);
        $this->assertSame(-0.0816327, $snapshot->chirality);
        $this->assertSame(0.1428571, $snapshot->closure);
        $this->assertSame(-0.011742, $snapshot->tension);
        $this->assertSame('settlement', $result['last_commitment']['domain_after']);
        $this->assertSame(-1.0, $result['last_commitment']['tension_before']);
        $this->assertFalse($result['last_commitment']['closure_boundary']);
        $this->assertSame(2, $result['horizon_sequence']);
        $this->assertSame(2, $phase->cycle->contextHorizon->sequence);
        $this->assertSame($snapshot->proposition_id, $this->participant->fresh()->current_proposition_id);
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->publishPotentialFixture($this->participant);
        $this->assertSame($result['last_commitment'], app(ParticipationService::class)->stateFor($this->participant)['last_commitment']);
    }

    public function test_an_observed_commit_retries_after_completion_even_when_bootstrap_remains_admissible(): void
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
        $cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $this->publishPotentialFixture($this->participant);
        $this->assertTrue(app(ClosureGateService::class)->preview($gate, $phase)['admissible']);
        $before = CommitmentSnapshot::count();
        try {
            app(ParticipationService::class)->contribute($this->participant, $phase->id,
                ['action' => 'proposition.commit', 'proposition_id' => $gate->id], $observed);
            $this->fail('A changed observed interval must retry even if its direction still aligns.');
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $error) {
            $this->assertStringContainsString('Closure changed', $error->getMessage());
        }
        $this->assertSame($before, CommitmentSnapshot::count());
        $this->assertNull($phase->fresh()->completed_at);
    }

    public function test_commit_freezes_source_attestation_direction_as_the_next_region_frame(): void
    {
        $this->bootstrap();
        [$phase, $gate] = $this->candidate([2], direction: 'refute');
        $this->assertSame(ObservableDirection::Negative, app(AttestationEvidenceService::class)->directionFor($gate));
        $this->publishPotentialFixture($this->participant);
        $state = app(ParticipationService::class)->contribute($this->participant, $phase->id,
            ['action' => 'proposition.commit', 'proposition_id' => $gate->id]);
        $commit = Proposition::findOrFail($state['current_proposition_id']);
        $this->assertSame(['governing' => ObservableDirection::Negative, 'support' => ObservableDirection::Negative],
            app(CommitRegionPolarityService::class)->forSeed($commit));
        $this->assertSame(['governing' => ObservableDirection::Positive, 'support' => ObservableDirection::Positive],
            app(CommitRegionPolarityService::class)->forSeed($this->origin));
        $this->assertSame($gate->statement, $commit->statement);
    }

    public function test_negative_closure_alignment_can_commit_while_corrected_field_remains_in_settlement(): void
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
        $this->publishPotentialFixture($this->participant);
        $state = app(ParticipationService::class)->contribute($this->participant, $phase->id, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
        // Negative retained directions still align. The ordinary structural
        // comparison changes the combined field, not Closure admissibility.
        $this->assertFalse($state['last_commitment']['closure_boundary']);
        $this->assertSame('settlement', $state['domain']);
        $this->assertSame(2, $state['horizon_sequence']);
        $snapshot = CommitmentSnapshot::findOrFail($state['current_proposition_id']);
        $this->assertSame(-0.3999863, $snapshot->correspondence);
        $this->assertSame(-0.0273297, $snapshot->chirality);
        $this->assertSame(0.1428571, $snapshot->closure);
        $this->assertSame(-0.0948196, $snapshot->tension);
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

    public function test_zero_depth_retains_negative_history_and_blocks_a_positive_cadence(): void
    {
        $this->bootstrap();
        $this->zeroDepthLocalValues();
        [$phase, $gate] = $this->candidate([2]);
        $counts = $this->counts();
        $this->publishPotentialFixture($this->participant);
        $state = app(ParticipationService::class)->stateFor($this->participant);
        $closure = $state['plan']['commitment']['closure'];
        $this->assertSame(0.0, $closure['depth']);
        $this->assertSame('negative', $closure['depth_direction']);
        $this->assertSame('positive', $closure['cadence_direction']);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertContains('depth_cadence_directions_diverge', $state['plan']['commitment']['reasons']);
        $this->assertArrayNotHasKey('proposition.commit', $state['plan']['schema']['$defs']['actions']);
        $this->assertSame(['participant.rest'], array_keys($state['plan']['schema']['$defs']['actions']));
        try {
            app(ContributionDispatcher::class)->dispatch($this->participant, $phase, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
            $this->fail('Direct dispatch must not bypass Closure.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('depth_cadence_directions_diverge', $exception->getMessage());
        }
        $this->assertSame($counts, $this->counts());
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertSame($gate->id, $this->participant->fresh()->current_proposition_id);
    }

    public function test_unrelated_newer_snapshot_does_not_supply_lineage_history(): void
    {
        $this->bootstrap();
        $this->zeroDepthLocalValues();
        [$phase, $gate] = $this->candidate([2]);
        $sibling = $this->node($phase, 'committal', 'concord');
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill([
            ...$this->origin->commitmentSnapshot->getAttributes(),
            'proposition_id' => $sibling->id,
            'ledger_entry_id' => $sibling->ledgerEntry->id,
            'supersession_id' => null,
            'depth_direction' => ObservableDirection::Positive,
            'cadence_direction' => ObservableDirection::Positive,
        ])->save();
        $service = app(ClosureGateService::class);
        $this->assertSame($this->origin->id, $service->historyFor($gate)->proposition_id);
        $this->assertFalse($service->preview($gate, $phase)['admissible']);

        $merged = $this->node($phase, 'expressive', 'concord');
        $this->edge($phase, $gate, $merged, 'reconcile');
        $this->edge($phase, $sibling, $merged, 'reconcile');
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
            $this->assertStringContainsString('aligned Depth/Cadence', $exception->getMessage());
        }
        $this->assertSame($counts, $this->counts());
        $this->assertSame($gate->id, $this->participant->fresh()->current_proposition_id);
        $this->assertNull($phase->fresh()->completed_at);
        $this->publishPotentialFixture($this->participant);
        $this->assertSame('settlement', app(ParticipationService::class)->stateFor($this->participant)['domain']);
    }

    public function test_legacy_missing_components_use_explicit_default_without_rewriting_history(): void
    {
        $this->bootstrap();
        $snapshot = $this->origin->commitmentSnapshot;
        // Simulate the nullable columns on a pre-migration snapshot.
        $snapshot->forceFill(['depth' => null, 'cadence' => null, 'depth_direction' => null, 'cadence_direction' => null])->save();
        $before = $snapshot->fresh()->getAttributes();
        [$phase, $gate] = $this->candidate([2]);
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $this->assertSame('committed-ancestor', $preview['history_source']);
        $this->assertSame($this->origin->id, $preview['history_proposition_id']); // Nullable legacy components retain explicit defaults on this boundary.
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
    }

    private function zeroDepthLocalValues(): void
    {
        // Exercise the zero-direction hysteresis rule independently of the
        // cycle-depth fixture, whose aggregate is no longer exactly zero.
        $this->mock(LocalMeasurementService::class, function ($mock): void {
            $mock->shouldReceive('calculate')->andReturn(new LocalMeasurements(0, 0, 0, 1));
        });
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
            $gate = $this->node($this->phaseAtPositionFixture($phase, PhaseKind::Bias), 'expressive', $sequence === end($cycles) ? $writeRegister : $gateRegister);
            $this->edge($phase, $previous, $gate, 'variant');
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

    private function node(Phase $phase, string $tier, string $register): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => $tier, 'register' => $register, 'statement' => 'Fixture proposition.'])->save();
        app(LedgerService::class)->record($node);

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
