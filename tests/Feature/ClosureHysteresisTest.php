<?php

namespace Tests\Feature;

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
use App\Models\Supersession;
use App\Services\ClosureGateService;
use App\Services\CommitmentSnapshotService;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;
use App\Services\OperationalInstrumentService;
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

    public function test_read_only_preview_matches_actual_commit_and_exposes_domain_crossing(): void
    {
        $this->bootstrap(2);
        [$phase, $gate] = $this->candidate([2]);
        $counts = $this->counts();
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $state = app(ParticipationService::class)->stateFor($this->participant);
        $this->assertSame($counts, $this->counts());
        // Raw candidate/gate/provenance: Depth (1, 0, -1), Cadence (1, 1, -1).
        $mass = 1 + 1 / 4 + 1 / 9;
        $this->assertSame(round((1 - 1 / 9) / $mass, 7), $preview['depth']);
        $this->assertSame(round((1 + 1 / 4 - 1 / 9) / $mass, 7), $preview['cadence']);
        $this->assertSame('ready', $state['status']);
        $this->assertTrue($state['plan']['commitment']['closure_enforced']);

        $result = app(ParticipationService::class)->contribute($this->participant, $phase->id, ['action' => 'proposition.commit']);
        $snapshot = CommitmentSnapshot::findOrFail($result['current_proposition_id']);
        $this->assertSame($preview['depth'], $snapshot->depth);
        $this->assertSame($preview['cadence'], $snapshot->cadence);
        $this->assertSame($preview['depth_direction'], $snapshot->depth_direction->value);
        $this->assertSame($preview['history_proposition_id'], $snapshot->closure_history_proposition_id);
        $this->assertSame('settlement', $result['last_commitment']['domain_before']);
        $this->assertSame('emergence', $result['last_commitment']['domain_after']);
        $this->assertSame(-1.0, $result['last_commitment']['tension_before']);
        $this->assertTrue($result['last_commitment']['closure_boundary']);
        $this->assertSame(3, $result['horizon_sequence']);
        $this->assertSame($snapshot->proposition_id, $this->participant->fresh()->current_proposition_id);
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->assertSame($result['last_commitment'], app(ParticipationService::class)->stateFor($this->participant)['last_commitment']);
    }

    public function test_negative_alignment_can_commit_without_changing_domain(): void
    {
        $this->bootstrap(1);
        [$phase, $gate] = $this->candidate([2, 3, 6]);
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $this->assertLessThan(0, $preview['depth']);
        // Raw cycle-interval Cadence at candidate, three gates, provenance.
        $this->assertSame(round((-1 - 1 / 4 + 1 / 9 + 1 / 16 - 1 / 25)
            / (1 + 1 / 4 + 1 / 9 + 1 / 16 + 1 / 25), 7), $preview['cadence']);
        $this->assertTrue($preview['admissible']);
        $state = app(ParticipationService::class)->contribute($this->participant, $phase->id, ['action' => 'proposition.commit']);
        $this->assertFalse($state['last_commitment']['closure_boundary']);
        $this->assertSame('settlement', $state['domain']);
        $this->assertSame(2, $state['horizon_sequence']);
        $snapshot = CommitmentSnapshot::findOrFail($state['current_proposition_id']);
        $this->assertSame($preview['depth'], $snapshot->depth);
        $this->assertSame($preview['cadence'], $snapshot->cadence);
    }

    public function test_zero_depth_retains_negative_history_and_blocks_a_positive_cadence(): void
    {
        $this->bootstrap(1);
        $this->zeroDepthLocalValues();
        [$phase, $gate] = $this->candidate([2]);
        $counts = $this->counts();
        $state = app(ParticipationService::class)->stateFor($this->participant);
        $closure = $state['plan']['commitment']['closure'];
        $this->assertSame(0.0, $closure['depth']);
        $this->assertSame('negative', $closure['depth_direction']);
        $this->assertSame('positive', $closure['cadence_direction']);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertContains('depth_cadence_directions_diverge', $state['plan']['commitment']['reasons']);
        $this->assertArrayNotHasKey('proposition.commit', $state['plan']['schema']['$defs']['actions']);
        $this->assertArrayHasKey('participant.rest', $state['plan']['schema']['$defs']['actions']);
        try {
            app(ContributionDispatcher::class)->dispatch($this->participant, $phase, ['action' => 'proposition.commit']);
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
        $this->bootstrap(1);
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
        $this->assertSame($sibling->id, $service->historyFor($merged)->proposition_id);
    }

    public function test_actual_candidate_rejection_rolls_back_a_previously_admissible_preview(): void
    {
        $this->bootstrap(2);
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
            app(ContributionDispatcher::class)->dispatch($this->participant, $phase, ['action' => 'proposition.commit']);
            $this->fail('An actual candidate rejection must roll back the write.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('aligned Depth/Cadence', $exception->getMessage());
        }
        $this->assertSame($counts, $this->counts());
        $this->assertSame($gate->id, $this->participant->fresh()->current_proposition_id);
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertSame('settlement', app(ParticipationService::class)->stateFor($this->participant)['domain']);
    }

    public function test_legacy_missing_components_use_explicit_default_without_rewriting_history(): void
    {
        $this->bootstrap(1);
        $snapshot = $this->origin->commitmentSnapshot;
        // Simulate the nullable columns on a pre-migration snapshot.
        $snapshot->forceFill(['depth' => null, 'cadence' => null, 'depth_direction' => null, 'cadence_direction' => null])->save();
        $before = $snapshot->fresh()->getAttributes();
        [$phase, $gate] = $this->candidate([2]);
        $preview = app(ClosureGateService::class)->preview($gate, $phase);
        $this->assertSame('negative-default', $preview['history_source']);
        $this->assertNull($preview['history_proposition_id']);
        $this->assertSame($before, $snapshot->fresh()->getAttributes());
    }

    private function zeroDepthLocalValues(): void
    {
        // Exercise the zero-direction hysteresis rule independently of the
        // cycle-depth fixture, whose aggregate is no longer exactly zero.
        $this->mock(\App\Services\Observables\LocalMeasurementService::class, function ($mock): void {
            $mock->shouldReceive('calculate')->andReturn(new \App\Domain\Observables\LocalMeasurements(0, 0, 0, 1));
        });
    }

    private function bootstrap(int $depth): void
    {
        $phase = $this->phase(1, 'canon');
        $previous = null;
        for ($i = 0; $i < $depth; $i++) {
            $node = $this->node($phase, $i === $depth - 1 ? 'committal' : 'expressive', $i === $depth - 1 ? 'canon' : 'activity');
            if ($previous !== null) {
                $this->edge($phase, $previous, $node, 'commit');
            }
            $previous = $node;
        }
        $this->origin = $previous;
        app(PropositionMeasurementService::class)->recalculate($this->origin);
        $snapshot = app(CommitmentSnapshotService::class)->record($this->origin, 0, 1, 'fixture-bootstrap');
        $this->assertSame(ObservableDirection::Negative, $snapshot->depth_direction);
        $this->assertSame(ObservableDirection::Negative, $snapshot->cadence_direction);
        $this->finish($phase);
    }

    /** @param list<int> $cycles @return array{Phase, Proposition} */
    private function candidate(array $cycles): array
    {
        $previous = $this->origin;
        foreach ($cycles as $sequence) {
            $phase = $this->phase($sequence, 'concord');
            $gate = $this->node($phase, 'expressive', 'signal');
            $this->edge($phase, $previous, $gate, 'variant');
            $phase->gate_proposition_id = $gate->id;
            $phase->save();
            if ($sequence !== end($cycles)) {
                $this->finish($phase);
            }
            $previous = $gate;
        }
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $phase->id, 'proposition_id' => $gate->id, 'direction' => 'support'])->save();
        app(LedgerService::class)->record($attestation);
        app(OperationalInstrumentService::class)->execute($phase, $gate, ['kind' => 'hyperlink', 'reference' => 'https://example.test/evidence']);
        $this->participant->current_proposition_id = $gate->id;
        $this->participant->save();
        return [$phase, $gate];
    }

    private function phase(int $sequence, string $register): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $this->participant->id, 'sequence' => $sequence])->save();
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
        return [Proposition::count(), Supersession::count(), CommitmentSnapshot::count(), ContextHorizon::count(), \App\Models\SubstrateLedgerEntry::count()];
    }
}
