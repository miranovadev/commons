<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ClosureGateService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContributionDispatcher;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipationService;
use App\Services\PropositionStateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalCommitmentTest extends TestCase
{
    use RefreshDatabase;

    private Participant $participant;

    private Proposition $origin;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('schemas.cache.store', 'array');
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'operational', 'title' => 'Operational',
            'visibility' => 'private', 'configuration' => [],
        ])->save();
        $this->participant = $this->participant($substrate, 'author');
        $state = app(ParticipationService::class)->enter($this->participant, [
            'statement' => 'The Origin.',
            'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]);
        $this->origin = Proposition::findOrFail($state['plan']['gate_id']);
        // These tests isolate the operational receipt/phase path. Real component
        // evaluation and refusal are covered by ClosureHysteresisTest.
        $closure = [
            ...(new ClosureValues(1, 1))->resolve(),
            'history_proposition_id' => null,
            'history_source' => 'negative-default',
        ];
        $this->mock(ClosureGateService::class, function ($mock) use ($closure): void {
            $mock->shouldReceive('preview', 'forCommitment')->andReturn($closure);
        });
    }

    public function test_blocked_commitment_rechecks_grounding_and_does_not_advance(): void
    {
        $gate = $this->positionedCandidate(PhaseKind::Bias);
        $phase = $this->phase($this->participant($this->participant->substrate, 'committer'), PhaseKind::Order, 'concord', $gate);
        $participant = $phase->cycle->participant;
        $this->publishPotentialFixture($participant);
        $state = app(ParticipationService::class)->stateFor($participant);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertArrayNotHasKey('attestation.create', $state['plan']['schema']['$defs']['actions']);
        $this->assertFalse($state['plan']['commitment']['requirements']['receipt']);
        try {
            app(ContributionDispatcher::class)->dispatch($participant, $phase, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
            $this->fail('Dispatch must enforce the same preconditions as the schema.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('effective Attestation', $exception->getMessage());
        }
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertDatabaseCount('commitment_snapshots', 1);
        $this->attest($gate);
        app(OperationalInstrumentService::class)->execute($gate->phase, $gate, [
            'kind' => 'hyperlink', 'reference' => 'https://example.test/ready',
        ]);
        $this->publishPotentialFixture($participant);
        $this->assertSame('ready', app(ParticipationService::class)->stateFor($participant)['status']);
    }

    public function test_ancestor_and_superseded_attestation_receipts_do_not_ground_the_gate(): void
    {
        $gate = $this->candidate();
        $first = $this->attest($gate);
        $service = app(CommitmentAdmissibilityService::class);
        $this->assertFalse($service->grounding($gate)['admissible']); // Origin evidence is not inherited.
        $executions = app(InstrumentExecutionService::class);
        $execution = $executions->start($gate->phase, $gate, 'hyperlink', 'https://example.test/attestation');
        $this->assertFalse($service->grounding($gate)['requirements']['receipt']);
        $executions->complete($execution, $first);
        $this->assertTrue($service->grounding($gate)['admissible']);
        $this->loadedReceiptFixture($gate, $first);
        $this->assertTrue($service->grounding($gate)['admissible']);
        $this->attest($gate, $first);
        $this->assertFalse($service->grounding($gate)['requirements']['receipt']);
    }

    public function test_citation_resolves_attestations_and_verified_hyperlinks_have_signatures(): void
    {
        $gate = $this->candidate();
        $attestation = $this->attest($gate);
        $service = app(OperationalInstrumentService::class);
        $receipt = $service->execute($gate->phase, $gate, ['kind' => 'citation', 'reference' => $attestation->id]);
        $this->assertSame($attestation->id, $receipt->metadata['target_id']);
        $receipt = $service->execute($gate->phase, $gate, ['kind' => 'hyperlink', 'reference' => ' https://example.test/evidence ']);
        $this->assertSame('https://example.test/evidence', $receipt->reference);
        $this->assertTrue($receipt->metadata['content_verified']);
        $this->assertNotNull($receipt->signature);
        $this->assertNotNull($gate->fresh()->measurement);
        $this->expectException(DomainException::class);
        app(InstrumentExecutionService::class)->complete($receipt->instrumentExecution, $gate);
    }

    public function test_pending_and_failed_executions_are_visible_without_satisfying_grounding(): void
    {
        $gate = $this->positionedCandidate(PhaseKind::Bias);
        $this->attest($gate);
        $committer = $this->participant($this->participant->substrate, 'waiting-committer');
        $this->phase($committer, PhaseKind::Order, 'concord', $gate);
        $service = app(InstrumentExecutionService::class);
        $execution = $service->start($gate->phase, $gate, 'hyperlink', 'https://example.test/pending');
        $this->publishPotentialFixture($committer);
        $state = app(ParticipationService::class)->stateFor($committer);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertSame('pending', $this->workspaceCard($committer, $gate)['instruments'][0]['status']);
        $this->assertNull($this->workspaceCard($committer, $gate)['instruments'][0]['receipt_id']);

        $service->fail($execution, 'Instrument unavailable.');
        $this->publishPotentialFixture($committer);
        $state = app(ParticipationService::class)->stateFor($committer);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertSame('failed', $this->workspaceCard($committer, $gate)['instruments'][0]['status']);
        $this->assertSame('Instrument unavailable.', $this->workspaceCard($committer, $gate)['instruments'][0]['failure_reason']);
        $this->assertFalse($state['plan']['commitment']['requirements']['receipt']);
    }

    public function test_invalid_and_unsupported_instruments_leave_no_partial_write(): void
    {
        $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Signal']);
        $state = $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Persist the distinction']);
        foreach ([
            ['kind' => 'hyperlink', 'reference' => 'file:///etc/passwd'],
            ['kind' => 'hyperlink', 'reference' => 'https://user:password@example.test'],
            ['kind' => 'citation', 'reference' => 'missing'],
        ] as $instrument) {
            try {
                $this->move($this->participant, ['action' => 'proposition.reclassify', 'instrument' => $instrument]);
                $this->fail('Invalid instrument should fail.');
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseMissing('propositions', ['statement' => 'Rejected']);
                $this->assertNull(Phase::findOrFail($state['plan']['phase']['id'])->completed_at);
                $this->assertDatabaseCount('instrument_executions', 2);
            }
        }
        $this->expectException(DomainException::class);
        app(OperationalInstrumentService::class)->execute($this->origin->phase, $this->origin, ['kind' => 'artefact', 'reference' => 'anything']);
    }

    public function test_cross_substrate_citation_is_rejected(): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'other', 'title' => 'Other', 'visibility' => 'private', 'configuration' => []])->save();
        $other = $this->participant($substrate, 'other');
        $foreign = new Proposition;
        $foreign->forceFill([
            'phase_id' => $this->phase($other, PhaseKind::Distinguish, 'signal', $this->origin)->id,
            'register' => 'signal', 'tier' => 'expressive', 'statement' => 'Foreign',
        ])->save();
        $this->expectException(DomainException::class);
        app(OperationalInstrumentService::class)->execute($this->origin->phase, $this->origin, ['kind' => 'citation', 'reference' => $foreign->id]);
    }

    public function test_bias_grounding_waits_for_another_participants_attestation_before_commit(): void
    {
        $left = $this->positionedCandidate(PhaseKind::Mediate);
        $this->classificationEvidenceFixture($left);
        $builder = $this->participant($this->participant->substrate, 'orienter');
        $biasPhase = $this->phase($builder, PhaseKind::Bias, 'concord', $left);
        $candidate = app(ContributionDispatcher::class)->dispatch($builder, $biasPhase, $this->reclassificationInputFixture($biasPhase, $left));
        // Another lineage does not erase the existing Concord.
        // Historical soft descendants remain visible without promoting their source.
        $this->positionedCandidate(PhaseKind::Disperse, $candidate);
        $reader = $this->participant($this->participant->substrate, 'reader');
        $bias = $this->phase($reader, PhaseKind::Bias, 'concord', $left);
        $this->publishPotentialFixture($reader);
        $state = app(ParticipationService::class)->stateFor($reader);
        $this->assertContains($candidate->id, array_column($state['plan']['write_options'], 'id'));
        $this->assertContains($candidate->id, $state['plan']['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $state = $this->move($reader, [
            'action' => 'instrument.execute', 'proposition_id' => $candidate->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/concord'],
        ]);
        $this->assertSame('order', $state['plan']['phase']['kind']);
        $this->assertTrue($state['plan']['commitment']['requirements']['receipt']);
        $this->assertFalse($state['plan']['commitment']['requirements']['attestation']);
        $receipt = $candidate->instrumentExecutions()->with('receipt')->firstOrFail()->receipt;
        $this->assertSame(['participant.rest'], array_keys($state['plan']['schema']['$defs']['actions']));
        $attestor = $this->participant($this->participant->substrate, 'attestor');
        $this->phase($attestor, PhaseKind::Bias, 'concord', $left);
        $this->move($attestor, ['action' => 'attestation.create', 'proposition_id' => $candidate->id, 'direction' => 'support']);
        $this->publishPotentialFixture($reader);
        $this->assertTrue(app(ParticipationService::class)->stateFor($reader)['plan']['commitment']['admissible']);
        $this->move($reader, ['action' => 'proposition.commit']);
        $committed = Proposition::findOrFail($reader->fresh()->current_proposition_id);
        $this->assertSame('committal', $committed->tier);
        $this->assertSame($candidate->id, $receipt->fresh()->receiptable_id);
        $this->assertSame(0, $committed->instrumentExecutions()->count());
    }

    public function test_removed_reclassification_contracts_reject_payloads(): void
    {
        $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Signal']);
        $early = $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Persist the distinction']);
        $earlyGate = Proposition::findOrFail($early['plan']['gate_id']);
        app(OperationalInstrumentService::class)->execute($earlyGate->phase, $earlyGate, ['kind' => 'citation', 'reference' => $this->origin->id]);
        foreach (['reclassify_with_receipt', 'proposition.reclassify_with_receipt', 'proposition.reclassify_with_instrument'] as $action) {
            try {
                $this->move($this->participant, ['action' => $action, 'statement' => 'Rejected']);
                $this->fail('Removed actions must fail validation.');
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseMissing('propositions', ['statement' => 'Rejected']);
            }
        }
    }

    public function test_constrain_instrument_targets_are_specialised_and_execute_normally(): void
    {
        [$reader, $phase, $candidate] = $this->instrumentFixture(PhaseKind::Constrain);
        $this->publishPotentialFixture($reader);
        $state = app(ParticipationService::class)->stateFor($reader);
        $this->assertSame([$candidate->id], $state['plan']['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $state = $this->move($reader, ['action' => 'instrument.execute', 'proposition_id' => $candidate->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/constrain']]);
        $this->assertSame('disperse', $state['plan']['phase']['kind']);
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->assertNull($phase->cycle->fresh()->completed_at);
        $this->assertSame($candidate->id, $candidate->instrumentExecutions()->with('receipt')->firstOrFail()->receipt->receiptable_id);
    }

    public function test_order_rejects_pre_terminal_actions_even_on_a_receipted_write(): void
    {
        [$reader, $phase, $candidate] = $this->instrumentFixture(PhaseKind::Order);
        $this->receiptFixture($candidate);
        $receipt = app(PropositionStateService::class)->receipts($candidate)->firstOrFail();
        $before = [Phase::count(), Attestation::count(), Receipt::count(), InstrumentExecution::count()];
        foreach ([
            ['action' => 'attestation.create', 'proposition_id' => $candidate->id, 'direction' => 'support'],
            ['action' => 'instrument.execute', 'proposition_id' => $candidate->id, 'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/order']],
            ['action' => 'receipt.read', 'receipt_id' => $receipt->id],
            ['action' => 'receipt.replay', 'receipt_id' => $receipt->id],
        ] as $input) {
            try {
                $this->publishPotentialFixture($reader);
                app(ParticipationService::class)->contribute($reader, $phase->id, $input);
                $this->fail('Order must reject pre-terminal actions.');
            } catch (\InvalidArgumentException|DomainException) {
                $this->assertNull($phase->fresh()->completed_at);
                $this->assertNull($phase->cycle->fresh()->completed_at);
                $this->assertSame($phase->gate_proposition_id, $reader->fresh()->current_proposition_id);
                $this->assertSame($before, [Phase::count(), Attestation::count(), Receipt::count(), InstrumentExecution::count()]);
            }
        }
    }

    public function test_invalid_order_actions_leave_phase_cycle_position_and_evidence_unchanged(): void
    {
        [$reader, $phase, $candidate] = $this->instrumentFixture(PhaseKind::Order);
        $before = [Phase::count(), Attestation::count(), Receipt::count(), InstrumentExecution::count()];
        foreach ([
            ['action' => 'attestation.create', 'proposition_id' => $this->origin->id, 'direction' => 'support'],
            ['action' => 'instrument.execute', 'proposition_id' => $this->origin->id, 'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test']],
            ['action' => 'instrument.execute', 'proposition_id' => $candidate->id, 'instrument' => ['kind' => 'hyperlink', 'reference' => 'file:///invalid']],
            ['action' => 'proposition.commit', 'proposition_id' => $candidate->id],
        ] as $input) {
            try {
                $this->publishPotentialFixture($reader);
                app(ParticipationService::class)->contribute($reader, $phase->id, $input);
                $this->fail('Invalid contribution should fail.');
            } catch (\InvalidArgumentException|DomainException) {
                $this->assertNull($phase->fresh()->completed_at);
                $this->assertNull($phase->cycle->fresh()->completed_at);
                $this->assertSame($phase->gate_proposition_id, $reader->fresh()->current_proposition_id);
                $this->assertSame($before, [Phase::count(), Attestation::count(), Receipt::count(), InstrumentExecution::count()]);
            }
        }
    }

    private function workspaceCard(Participant $participant, Proposition $node): array
    {
        $this->publishPotentialFixture($participant);
        $plan = app(ParticipationService::class)->workspaceFor($participant)['plan'];

        return collect($plan['workspace']['registers'])->flatten(1)->firstWhere('id', $node->id);
    }

    private function instrumentFixture(PhaseKind $kind): array
    {
        $sourcePosition = $kind === PhaseKind::Constrain ? PhaseKind::Persist : PhaseKind::Mediate;
        $stance = $this->positionedCandidate($sourcePosition);
        $candidate = $this->positionedCandidate($kind === PhaseKind::Order ? PhaseKind::Bias : $kind, $stance);
        $reader = $this->participant($this->participant->substrate, 'reader-'.str()->ulid());
        $gate = $kind === PhaseKind::Order ? $candidate : $stance;
        $reader->forceFill(['current_proposition_id' => $gate->id])->save();

        return [$reader, $this->phase($reader, $kind, 'concord', $gate), $candidate];
    }

    private function positionedCandidate(PhaseKind $position, ?Proposition $parent = null): Proposition
    {
        $parent ??= $this->origin;
        $phase = $this->phase($this->participant($this->participant->substrate, 'builder-'.str()->ulid()),
            $position, ContextDomain::Settlement->registerFor($position), $parent);
        $candidate = new Proposition;
        $candidate->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => 'expressive', 'statement' => 'Prepared '.$position->name])->save();
        app(LedgerService::class)->record($candidate);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $candidate->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);

        return $candidate;
    }

    private function candidate(): Proposition
    {
        $state = $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Candidate']);

        return Proposition::findOrFail($state['plan']['gate_id']);
    }

    private function attest(Proposition $gate, ?Attestation $previous = null): Attestation
    {
        $phase = $gate->phase;
        if ($previous !== null) {
            $phase = $this->phase($phase->cycle->participant, PhaseKind::Constrain, $gate->register, $gate);
        }
        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $phase->id, 'proposition_id' => $gate->id,
            'direction' => $previous === null ? 'support' : 'refute',
            'supersedes_attestation_id' => $previous?->id,
        ])->save();
        app(LedgerService::class)->record($attestation);

        return $attestation;
    }

    private function participant(Substrate $substrate, string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign, 'max_read_count' => 25])->save();

        return $participant;
    }

    private function phase(Participant $participant, PhaseKind $kind, string $register, Proposition $gate): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->id,
            'context_horizon_id' => $this->horizonForRegisterFixture($participant, $register)->id,
            'sequence' => (int) $participant->cycles()->max('sequence') + 1,
        ])->save();
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register,
            'gate_proposition_id' => $gate->phase->cycle->participant->substrate_id === $participant->substrate_id ? $gate->id : null, 'started_at' => now(),
        ])->save();

        return $phase;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function move(Participant $participant, array $input): array
    {
        if ($input['action'] === 'proposition.reclassify') {
            $this->classificationEvidenceFixture($participant->fresh()->position);
        }
        if ($input['action'] === 'proposition.commit') {
            $input['proposition_id'] = $participant->fresh()->current_proposition_id;
        }
        $service = app(ParticipationService::class);
        $this->publishPotentialFixture($participant);
        $state = $service->stateFor($participant);
        if ($input['action'] === 'proposition.reclassify') {
            $input = $this->reclassificationInputFixture(Phase::findOrFail($state['plan']['phase']['id'])) + $input;
        }

        $this->publishPotentialFixture($participant);

        return $service->contribute($participant, $state['plan']['phase']['id'], $input);
    }
}
