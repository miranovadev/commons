<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PropositionOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class RevisionAdmissibilityTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    public function test_revision_targets_owned_heads_in_the_write_register_from_a_prior_cycle(): void
    {
        // Isolate structural rules from a new Charge-boundary event.
        $this->resolveExpressiveFieldFixture(.8);
        [$participant, $phase, $gate] = $this->currentPhase();
        $owned = $this->priorHead(
            $participant,
            'frontier',
            'An owned Frontier head.',
        );
        $wrongRegister = $this->priorHead(
            $participant,
            'activity',
            'An owned Activity head.',
        );
        $other = new Participant;
        $other->forceFill([
            'substrate_id' => $participant->substrate_id,
            'callsign' => 'other',
        ]);
        $other->save();
        $foreign = $this->priorHead(
            $other,
            'frontier',
            'Someone else owns this Frontier head.',
        );

        foreach ([$owned, $wrongRegister, $foreign] as $head) {
            $gate->children()->attach($head->id, [
                'id' => (string) str()->ulid(), 'phase_id' => $phase->id, 'type' => 'variant',
            ]);
        }

        $this->attest($owned->phase, $owned, 'refute');
        $plan = app(InputPlanService::class)->forParticipant($participant);

        $this->assertEqualsCanonicalizing(
            [$owned->getKey(), $foreign->getKey()],
            array_column($plan['write_options'], 'id'),
        );
        $this->assertSame(
            [$owned->getKey()],
            array_column($plan['revision_options'], 'id'),
        );
        $this->assertSame(
            [$owned->getKey()],
            $plan['schema']['$defs']['actions']['proposition.revise']['properties']['proposition_id']['enum'],
        );
        $this->assertNotContains(
            $gate->getKey(),
            array_column($plan['revision_options'], 'id'),
        );
        $this->assertNotContains(
            $wrongRegister->getKey(),
            array_column($plan['revision_options'], 'id'),
        );

        $target = app(ContributionDispatcher::class)->dispatch(
            $participant,
            $phase,
            [
                'action' => 'proposition.revise',
                'proposition_id' => $owned->getKey(),
                'statement' => 'The revised Frontier statement.',
            ],
        );

        $this->assertSame('frontier', $target->register);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $owned->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => 'revise',
        ]);
    }

    public function test_the_gate_cannot_be_revised_even_when_it_matches_the_write_register(): void
    {
        [$participant, $phase, $gate] = $this->currentPhase('frontier');
        $plan = app(InputPlanService::class)->forParticipant($participant);

        $this->assertArrayNotHasKey(
            'proposition.revise',
            $plan['schema']['$defs']['actions'],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Revision requires an owned active head in the current write register with an effective self-Refute from an earlier Cycle.'
        );

        app(ContributionDispatcher::class)->dispatch(
            $participant,
            $phase,
            [
                'action' => 'proposition.revise',
                'proposition_id' => $gate->getKey(),
                'statement' => 'This must never be written.',
            ],
        );
    }

    public static function ineligibleStances(): array
    {
        return [
            'no personal attestation' => [null, false, false],
            'effective Support' => ['support', false, false],
            'same opportunity Refute' => ['refute', true, false],
            'Refute superseded by Support' => ['refute', false, true],
        ];
    }

    #[DataProvider('ineligibleStances')]
    public function test_packet_and_direct_dispatch_reject_ineligible_stances(?string $direction, bool $samePhase, bool $supportAgain): void
    {
        [$participant, $phase, $gate, $head] = $this->revisionFixture();
        if ($direction !== null) {
            $previous = $this->attest($samePhase ? $phase : $head->phase, $head, $direction);
            if ($supportAgain) {
                $this->attest($phase, $head, 'support', $previous);
            }
        }
        $this->assertRevisionUnavailable($participant, $phase, $head);
    }

    public function test_self_refute_unlocks_a_later_cycle_but_not_the_revised_child(): void
    {
        // Isolate structural rules from a new Charge-boundary event.
        $this->resolveExpressiveFieldFixture(.8);
        [$participant, $phase, $gate, $head] = $this->revisionFixture();
        $dispatch = app(ContributionDispatcher::class);
        $dispatch->dispatch($participant, $phase, [
            'action' => 'attestation.create', 'proposition_id' => $head->id, 'direction' => 'support',
        ]);
        $phase = $this->nextEncounter($phase);
        $this->assertRevisionUnavailable($participant, $phase, $head);
        $dispatch->dispatch($participant, $phase, [
            'action' => 'attestation.create', 'proposition_id' => $head->id, 'direction' => 'refute',
        ]);
        $this->assertRevisionUnavailable($participant, $phase, $head);
        $phase = $this->nextEncounter($phase);
        $plan = app(InputPlanService::class)->forParticipant($participant);
        $this->assertSame([$head->id], array_column($plan['revision_options'], 'id'));
        $this->assertSame([$head->id], $plan['schema']['$defs']['actions']['proposition.revise']['properties']['proposition_id']['enum']);
        $this->assertSame([$head->id], app(PropositionOptionService::class)->revisionOptionsFor($phase, $participant)->modelKeys());
        $input = ['action' => 'proposition.revise', 'proposition_id' => $head->id, 'statement' => 'Fresh revision'];
        app(ContributionService::class)->validate($participant, $input);
        $revised = $dispatch->dispatch($participant, $phase, $input);
        $this->assertSame([$head->id], $revised->parents()->pluck('propositions.id')->all());
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $head->id, 'target_proposition_id' => $revised->id, 'type' => 'revise',
        ]);
        $this->assertFalse($revised->effectiveAttestations()->exists());
        $this->assertRevisionUnavailable($participant, $phase, $revised);
        $phase = $this->nextEncounter($phase);
        $this->assertRevisionUnavailable($participant, $phase, $revised);
        $this->receiptFixture($revised);
        $support = ['action' => 'attestation.create', 'proposition_id' => $revised->id, 'direction' => 'support'];
        app(ContributionService::class)->validate($participant, $support);
        $dispatch->dispatch($participant, $phase, $support);
        $this->assertSame('support', $revised->effectiveAttestations()->sole()->direction);
    }

    public function test_switching_back_to_support_invalidates_a_previously_valid_revision_packet(): void
    {
        [$participant, $phase, $gate, $head] = $this->revisionFixture();
        $this->attest($head->phase, $head, 'refute');
        $this->assertNotEmpty(app(InputPlanService::class)->forParticipant($participant)['revision_options']);
        app(ContributionDispatcher::class)->dispatch($participant, $phase, [
            'action' => 'attestation.create', 'proposition_id' => $head->id, 'direction' => 'support',
        ]);
        $this->assertRevisionUnavailable($participant, $phase, $head);
        $this->assertRevisionUnavailable($participant, $this->nextEncounter($phase), $head);
    }

    public function test_a_personal_refute_does_not_allow_revision_of_another_participants_write(): void
    {
        [$participant, $phase, $gate] = $this->currentPhase();
        $other = new Participant;
        $other->forceFill(['substrate_id' => $participant->substrate_id, 'callsign' => 'other'])->save();
        $head = $this->priorHead($other, 'frontier', 'Foreign Write');
        $personalHistory = $this->priorHead($participant, 'activity', 'Personal history')->phase;
        $gate->children()->attach($head->id, ['id' => (string) str()->ulid(), 'phase_id' => $phase->id, 'type' => 'variant']);
        $this->attest($personalHistory, $head, 'refute');
        $this->assertRevisionUnavailable($participant, $phase, $head);
    }

    public function test_an_earlier_phase_in_the_same_cycle_does_not_unlock_revision(): void
    {
        [$participant, $phase, $gate, $head] = $this->revisionFixture();
        $earlier = new Phase;
        $earlier->forceFill(['cycle_id' => $phase->cycle_id, 'kind' => PhaseKind::Distinguish,
            'write_register' => 'frontier', 'started_at' => now(), 'completed_at' => now()])->save();
        $this->attest($earlier, $head, 'refute');
        $this->assertRevisionUnavailable($participant, $phase, $head);
    }

    private function assertRevisionUnavailable(Participant $participant, Phase $phase, Proposition $head): void
    {
        $this->publishPotentialFixture($participant);
        $state = app(ParticipationService::class)->stateFor($participant);
        $plan = app(InputPlanService::class)->forParticipant($participant);
        $this->assertSame([], $plan['revision_options']);
        $this->assertArrayNotHasKey('proposition.revise', $plan['schema']['$defs']['actions']);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($plan['schema'], $prompt['schema']);
        $this->assertFalse($plan['rest_available'], 'Support actions remain available.');
        $this->assertTrue(app(PropositionOptionService::class)->revisionOptionsFor($phase, $participant)->isEmpty());
        $input = ['action' => 'proposition.revise', 'proposition_id' => $head->id, 'statement' => 'Must not be written'];
        $counts = [Proposition::count(), Supersession::count(), $participant->substrate->ledgerEntries()->count()];
        try {
            app(ContributionService::class)->validate($participant, $input);
            $this->fail('Schema must reject revision.');
        } catch (InvalidArgumentException) {
        }
        try {
            app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
            $this->fail('Direct dispatch must reject revision.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('effective self-Refute from an earlier Cycle', $exception->getMessage());
        }
        $this->assertSame($counts, [Proposition::count(), Supersession::count(), $participant->substrate->ledgerEntries()->count()]);
    }

    private function revisionFixture(): array
    {
        [$participant, $phase, $gate] = $this->currentPhase();
        $head = $this->priorHead($participant, 'frontier', 'Owned Write');
        $gate->children()->attach($head->id, ['id' => (string) str()->ulid(), 'phase_id' => $phase->id, 'type' => 'variant']);

        $this->receiptFixture($head);

        return [$participant, $phase, $gate, $head];
    }

    private function attest(Phase $phase, Proposition $head, string $direction, ?Attestation $previous = null): Attestation
    {
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $phase->id, 'proposition_id' => $head->id,
            'direction' => $direction, 'supersedes_attestation_id' => $previous?->id])->save();
        app(LedgerService::class)->record($attestation);

        return $attestation;
    }

    private function nextEncounter(Phase $previous): Phase
    {
        $previous->forceFill(['completed_at' => now()])->save();
        $previous->cycle->forceFill(['completed_at' => now()])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $previous->cycle->participant_id,
            'sequence' => $previous->cycle->sequence + 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $previous->kind,
            'write_register' => $previous->write_register, 'gate_proposition_id' => $previous->gate_proposition_id,
            'started_at' => now()])->save();

        return $phase;
    }

    /**
     * @return array{Participant, Phase, Proposition}
     */
    private function currentPhase(
        string $gateRegister = 'canon',
    ): array {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'revision-admissibility',
            'title' => 'Revision admissibility',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $substrate->save();

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'owner',
            'max_read_count' => 25,
            'max_token_count' => null,
        ]);
        $participant->save();

        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 2,
        ]);
        $cycle->save();

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Persist,
            'write_register' => 'frontier',
            'started_at' => now(),
        ]);
        $phase->save();

        $gate = new Proposition;
        $gate->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => $gateRegister,
            'tier' => 'expressive',
            'statement' => 'The current Gate.',
        ]);
        $gate->save();
        app(LedgerService::class)->record($gate);
        $phase->gate_proposition_id = $gate->getKey();
        $phase->save();

        return [$participant, $phase, $gate];
    }

    private function priorHead(
        Participant $participant,
        string $register,
        string $statement,
    ): Proposition {
        $cycle = Cycle::query()
            ->whereBelongsTo($participant)
            ->whereNotNull('completed_at')
            ->first();

        if ($cycle === null) {
            $cycle = new Cycle;
            $cycle->forceFill([
                'participant_id' => $participant->getKey(),
                'sequence' => 1,
                'completed_at' => now(),
            ]);
            $cycle->save();
        }

        $phase = Phase::query()->whereBelongsTo($cycle)->first();

        if ($phase === null) {
            $phase = new Phase;
            $phase->forceFill([
                'cycle_id' => $cycle->getKey(),
                'kind' => PhaseKind::Persist,
                'write_register' => 'frontier',
                'started_at' => now()->subMinute(),
                'completed_at' => now(),
            ]);
            $phase->save();
        }

        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => $register,
            'tier' => 'expressive',
            'statement' => $statement,
        ]);
        $proposition->save();
        app(LedgerService::class)->record($proposition);

        return $proposition;
    }
}
