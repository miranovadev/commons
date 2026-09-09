<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContributionDispatcher;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipationService;
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
        $this->origin = Proposition::findOrFail($state['plan']['gate']['id']);
        // These tests isolate the operational receipt/phase path. Real component
        // evaluation and refusal are covered by ClosureHysteresisTest.
        $closure = [
            ...(new \App\Domain\Observables\ClosureValues(1, 1))->resolve(),
            'history_proposition_id' => null,
            'history_source' => 'negative-default',
        ];
        $this->mock(\App\Services\ClosureGateService::class, function ($mock) use ($closure): void {
            $mock->shouldReceive('preview', 'forCommitment')->andReturn($closure);
        });
    }

    public function test_seven_phase_participation_completes_receipts_and_commits(): void
    {
        $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Signal']);
        $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Stance']);
        $state = $this->move($this->participant, [
            'action' => 'proposition.reclassify', 'statement' => 'Concord candidate',
        ]);
        $this->assertSame([], $state['plan']['gate']['instruments']);
        $this->assertDatabaseCount('instrument_executions', 1);
        $state = $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'New Signal']);

        // Other participants prepare local write-register heads through real contributions.
        $helper = $this->participant($this->participant->substrate, 'helper');
        $this->phase($helper, PhaseKind::Persist, 'stance', Proposition::findOrFail($state['plan']['gate']['id']));
        $helperState = $this->move($helper, ['action' => 'proposition.variant', 'statement' => 'Mediated Stance']);
        $stance = Proposition::findOrFail($helperState['plan']['gate']['id']);
        $this->move($this->participant, ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'support']);
        $helperState = $this->move($helper, [
            'action' => 'proposition.reclassify', 'statement' => 'Final Concord',
        ]);
        $candidate = Proposition::findOrFail($helperState['plan']['gate']['id']);
        $reviewer = $this->participant($this->participant->substrate, 'reviewer');
        $this->phase($reviewer, PhaseKind::Constrain, 'concord', $stance);
        $this->move($reviewer, [
            'action' => 'attestation.create', 'proposition_id' => $candidate->id,
            'direction' => 'support',
        ]);

        $state = $this->move($this->participant, [
            'action' => 'instrument.execute', 'proposition_id' => $candidate->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/final'],
        ]);
        $this->assertSame('order', $state['plan']['phase']['kind']);
        $this->assertSame('ready', $state['status']);
        $this->assertTrue($state['plan']['commitment']['admissible']);
        $this->assertTrue($state['plan']['commitment']['closure_enforced']);
        $this->assertTrue($state['plan']['commitment']['requirements']['closure']);
        $this->assertArrayHasKey('proposition.commit', $state['plan']['schema']['$defs']['actions']);
        $this->assertFalse($state['plan']['tragedy']);
        $this->assertArrayNotHasKey('participant.rest', $state['plan']['schema']['$defs']['actions']);

        $phaseId = $state['plan']['phase']['id'];
        $this->move($this->participant, ['action' => 'proposition.commit']);
        $committed = Proposition::findOrFail($this->participant->fresh()->current_proposition_id);
        $this->assertSame('committal', $committed->tier);
        $this->assertSame('concord', $committed->register);
        $this->assertSame($candidate->statement, $committed->statement);
        $this->assertNotNull($committed->commitmentSnapshot);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $candidate->id,
            'target_proposition_id' => $committed->id, 'type' => 'commit',
        ]);
        $this->assertNotNull(Phase::findOrFail($phaseId)->completed_at);
        $this->assertNotNull($committed->phase->cycle->completed_at);
        $this->assertDatabaseCount('commitment_snapshots', 2);
    }

    public function test_blocked_commitment_rechecks_grounding_and_does_not_advance(): void
    {
        $gate = $this->candidate();
        $phase = $this->phase($this->participant($this->participant->substrate, 'committer'), PhaseKind::Order, 'concord', $gate);
        $participant = $phase->cycle->participant;
        $state = app(ParticipationService::class)->stateFor($participant);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertArrayHasKey('participant.rest', $state['plan']['schema']['$defs']['actions']);
        $this->assertFalse($state['plan']['commitment']['requirements']['receipt']);
        try {
            app(ContributionDispatcher::class)->dispatch($participant, $phase, ['action' => 'proposition.commit']);
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
        $this->attest($gate, $first);
        $this->assertFalse($service->grounding($gate)['requirements']['receipt']);
    }

    public function test_citation_resolves_attestations_and_hyperlinks_remain_unsigned(): void
    {
        $gate = $this->candidate();
        $attestation = $this->attest($gate);
        $service = app(OperationalInstrumentService::class);
        $receipt = $service->execute($gate->phase, $gate, ['kind' => 'citation', 'reference' => $attestation->id]);
        $this->assertSame($attestation->id, $receipt->metadata['target_id']);
        $receipt = $service->execute($gate->phase, $gate, ['kind' => 'hyperlink', 'reference' => ' https://example.test/evidence ']);
        $this->assertSame('https://example.test/evidence', $receipt->reference);
        $this->assertFalse($receipt->metadata['content_verified']);
        $this->assertNull($receipt->signature);
        $this->assertNotNull($gate->fresh()->measurement);
        $this->expectException(DomainException::class);
        app(InstrumentExecutionService::class)->complete($receipt->instrumentExecution, $gate);
    }

    public function test_pending_and_failed_executions_are_visible_without_satisfying_grounding(): void
    {
        $gate = $this->candidate();
        $this->attest($gate);
        $committer = $this->participant($this->participant->substrate, 'waiting-committer');
        $this->phase($committer, PhaseKind::Order, 'concord', $gate);
        $service = app(InstrumentExecutionService::class);
        $execution = $service->start($gate->phase, $gate, 'hyperlink', 'https://example.test/pending');
        $state = app(ParticipationService::class)->stateFor($committer);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertSame('pending', $state['plan']['gate']['instruments'][0]['status']);
        $this->assertNull($state['plan']['gate']['instruments'][0]['receipt_id']);

        $service->fail($execution, 'Instrument unavailable.');
        $state = app(ParticipationService::class)->stateFor($committer);
        $this->assertSame('ready', $state['status']);
        $this->assertFalse($state['plan']['commitment']['admissible']);
        $this->assertSame('failed', $state['plan']['gate']['instruments'][0]['status']);
        $this->assertSame('Instrument unavailable.', $state['plan']['gate']['instruments'][0]['failure_reason']);
        $this->assertFalse($state['plan']['commitment']['requirements']['receipt']);
    }

    public function test_invalid_and_unsupported_instruments_leave_no_partial_write(): void
    {
        $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Signal']);
        $state = $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Stance']);
        foreach ([
            ['kind' => 'hyperlink', 'reference' => 'file:///etc/passwd'],
            ['kind' => 'hyperlink', 'reference' => 'https://user:password@example.test'],
            ['kind' => 'citation', 'reference' => 'missing'],
        ] as $instrument) {
            try {
                $this->move($this->participant, ['action' => 'proposition.reclassify', 'statement' => 'Rejected', 'instrument' => $instrument]);
                $this->fail('Invalid instrument should fail.');
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseMissing('propositions', ['statement' => 'Rejected']);
                $this->assertNull(Phase::findOrFail($state['plan']['phase']['id'])->completed_at);
                $this->assertDatabaseCount('instrument_executions', 1);
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

    public function test_bias_grounds_a_reconciled_historical_proposition_and_later_cycle_can_commit_it(): void
    {
        $left = $this->candidate();
        $right = new Proposition;
        $right->forceFill(['phase_id' => $left->phase_id, 'register' => $left->register,
            'tier' => 'expressive', 'statement' => 'Sibling alternative'])->save();
        app(LedgerService::class)->record($right);
        $this->origin->children()->attach($right->id, [
            'id' => (string) str()->ulid(), 'phase_id' => $left->phase_id, 'type' => 'variant',
        ]);
        $this->attest($right);
        $builder = $this->participant($this->participant->substrate, 'reconciler');
        $reconcilePhase = $this->phase($builder, PhaseKind::Bias, 'concord', $left);
        // Use the normal dispatcher to produce the two-parent reconciliation.
        $candidate = app(ContributionDispatcher::class)->dispatch($builder, $reconcilePhase, [
            'action' => 'proposition.reconcile', 'other_proposition_id' => $right->id,
        ]);
        // Another lineage does not erase the original reconciled Proposition.
        $descendant = app(ContributionDispatcher::class)->dispatch($builder, $reconcilePhase, [
            'action' => 'proposition.variant', 'statement' => 'Another lineage',
        ]);
        $descendant->register = 'signal';
        $descendant->save();
        $candidate->children()->attach($descendant->id, [
            'id' => (string) str()->ulid(), 'phase_id' => $reconcilePhase->id, 'type' => 'variant',
        ]);
        $reader = $this->participant($this->participant->substrate, 'reader');
        $bias = $this->phase($reader, PhaseKind::Bias, 'concord', $right);
        $state = app(ParticipationService::class)->stateFor($reader);
        $this->assertContains($candidate->id, array_column($state['plan']['write_options'], 'id'));
        $this->assertContains($candidate->id, $state['plan']['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $state = $this->move($reader, [
            'action' => 'instrument.execute', 'proposition_id' => $candidate->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/reconciled'],
        ]);
        $this->assertSame('order', $state['plan']['phase']['kind']);
        $this->assertTrue($state['plan']['commitment']['requirements']['receipt']);
        $this->assertFalse($state['plan']['commitment']['requirements']['attestation']);
        $receipt = $candidate->instrumentExecutions()->with('receipt')->firstOrFail()->receipt;
        $this->move($reader, ['action' => 'participant.rest']);
        $this->assertNotNull($bias->cycle->fresh()->rested_at);
        $this->attest($candidate);
        // Place a later Cycle at its Order revisit; recovery itself has separate graph tests.
        $later = $this->phase($reader, PhaseKind::Order, 'concord', $candidate);
        $this->assertGreaterThan($bias->cycle->sequence, $later->cycle->sequence);
        $state = app(ParticipationService::class)->stateFor($reader);
        $this->assertTrue($state['plan']['commitment']['admissible']);
        $this->move($reader, ['action' => 'proposition.commit']);
        $committed = Proposition::findOrFail($reader->fresh()->current_proposition_id);
        $this->assertSame('committal', $committed->tier);
        $this->assertSame($candidate->id, $receipt->fresh()->receiptable_id);
        $this->assertSame(0, $committed->instrumentExecutions()->count());
    }

    public function test_removed_reclassification_contracts_reject_payloads(): void
    {
        $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Signal']);
        $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Stance']);
        foreach (['reclassify_with_receipt', 'proposition.reclassify_with_receipt', 'proposition.reclassify_with_instrument'] as $action) {
            try {
                $this->move($this->participant, ['action' => $action, 'statement' => 'Rejected']);
                $this->fail('Removed actions must fail validation.');
            } catch (\InvalidArgumentException) {
                $this->assertDatabaseMissing('propositions', ['statement' => 'Rejected']);
            }
        }
    }

    private function candidate(): Proposition
    {
        $state = $this->move($this->participant, ['action' => 'proposition.variant', 'statement' => 'Candidate']);
        return Proposition::findOrFail($state['plan']['gate']['id']);
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
            'sequence' => (int) $participant->cycles()->max('sequence') + 1,
        ])->save();
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register,
            'gate_proposition_id' => $gate->id, 'started_at' => now(),
        ])->save();
        return $phase;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function move(Participant $participant, array $input): array
    {
        $service = app(ParticipationService::class);
        $state = $service->stateFor($participant);
        return $service->contribute($participant, $state['plan']['phase']['id'], $input);
    }
}
