<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Enums\PhaseKind;
use App\Enums\SupersessionStrength;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ActiveParticipantLineage;
use App\Services\ClosureGateService;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WriteOnceTest extends TestCase
{
    use RefreshDatabase;

    public static function localPhases(): array
    {
        return [
            'Distinguish' => [PhaseKind::Distinguish, 'activity', 'proposition.variant'],
            'Persist Variant' => [PhaseKind::Persist, 'frontier', 'proposition.variant'],
            'Persist Reclassify' => [PhaseKind::Persist, 'frontier', 'proposition.reclassify'],
            'Constrain' => [PhaseKind::Constrain, 'canon', 'proposition.reclassify'],
            'Disperse' => [PhaseKind::Disperse, 'activity', 'proposition.variant'],
        ];
    }

    public static function persistOwnership(): array
    {
        return [
            'own Gate, unused' => [false, false, false],
            'own Gate, historical Variant' => [false, true, false],
            'another owner, unused' => [true, false, true],
            'another owner, historical Variant' => [true, true, false],
        ];
    }

    #[DataProvider('persistOwnership')]
    public function test_persist_variant_ownership_and_history_match_schema_and_backend(bool $foreign, bool $used, bool $available): void
    {
        [$participant, $seed] = $this->fixture($foreign);
        $gate = $this->node($seed->phase, 'activity', 'Phase 1 Gate', parent: $seed);
        if ($used) {
            // Include legacy own-Gate Variants written before the ownership rule.
            $history = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
            $this->node($history, 'frontier', 'Prior Variant', parent: $gate);
        }
        $phase = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $input = ['action' => 'proposition.variant', 'statement' => 'Divergent Frontier'];
        $state = app(ParticipationService::class)->stateFor($participant);
        $this->assertSame($available, isset($state['plan']['schema']['$defs']['actions']['proposition.variant']));
        $packet = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($state['plan']['schema'], $packet['schema']);
        if (! $available) {
            $this->assertUnavailable($participant, $phase, $input);

            return;
        }
        $next = app(ParticipationService::class)->contribute($participant, $phase->id, $input);
        $written = Proposition::findOrFail($next['plan']['gate_id']);
        $this->assertSame('constrain', $next['plan']['phase']['kind']);
        $this->assertSame('variant', $written->parents()->sole()->pivot->type);
        $this->assertSame(SupersessionStrength::Soft, SupersessionStrength::for($written->parents()->sole()->pivot->type));
        $this->assertContains($gate->id, app(ActiveParticipantLineage::class)->endingAt($written)->pluck('id')->all());
        // Direct replay is rejected even before a new Cycle is opened.
        $counts = [Proposition::count(), Supersession::count()];
        try {
            app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
            $this->fail('The Variant opportunity must be consumed immediately.');
        } catch (DomainException) {
            $this->assertSame($counts, [Proposition::count(), Supersession::count()]);
        }
        $return = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $this->assertUnavailable($participant, $return, $input);
        $other = $this->participant($participant->substrate, 'independent');
        $otherPhase = $this->phase($other, PhaseKind::Persist, 'frontier', $gate);
        app(ContributionService::class)->validate($other, $input);
        $otherWrite = app(ContributionDispatcher::class)->dispatch($other, $otherPhase, $input);
        $this->assertNotSame($written->id, $otherWrite->id);
    }

    public function test_owned_persist_gate_retains_support_without_variant_or_unearned_revision(): void
    {
        [$participant, $seed] = $this->fixture();
        $gate = $this->node($seed->phase, 'activity', 'My distinction', parent: $seed);
        $phase = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $this->assertUnavailable($participant, $phase, ['action' => 'proposition.variant', 'statement' => 'Self Variant']);
        // A grounded and attested source can be classified by any Participant.
        $this->classificationEvidenceFixture($gate);
        $write = app(ContributionDispatcher::class)->dispatch($participant, $phase, [
            'action' => 'proposition.reclassify',
        ]);
        $this->receiptFixture($write);
        $return = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $plan = $this->plan($participant);
        $this->assertArrayNotHasKey('proposition.variant', $plan['schema']['$defs']['actions']);
        $this->assertSame([], $this->targets($plan, 'proposition.revise'));
        $this->assertContains($write->id, $this->targets($plan, 'attestation.create'));
        $this->assertContains($write->id, $this->targets($plan, 'instrument.execute'));
        $this->assertFalse($plan['tragedy']);
        $this->assertArrayNotHasKey('participant.rest', $plan['schema']['$defs']['actions']);
        app(ContributionDispatcher::class)->dispatch($participant, $return, [
            'action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'refute',
        ]);
        $plan = $this->plan($participant);
        $this->assertContains($write->id, $this->targets($plan, 'proposition.traverse'));
        $this->assertSame([], $this->targets($plan, 'proposition.revise'));
        $later = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $this->assertContains($write->id, $this->targets($this->plan($participant), 'proposition.revise'));
        $this->assertUnavailable($participant, $later, ['action' => 'proposition.variant', 'statement' => 'Still cannot self Variant']);
    }

    public function test_persist_revision_does_not_restore_the_foreign_gate_variant_opportunity(): void
    {
        [$participant, $seed] = $this->fixture(true);
        $gate = $this->node($seed->phase, 'activity', 'Another Participant’s distinction', parent: $seed);
        $phase = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $input = ['action' => 'proposition.variant', 'statement' => 'First Variant'];
        $write = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
        $this->receiptFixture($write);
        app(ContributionDispatcher::class)->dispatch($participant, $phase, [
            'action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'refute',
        ]);
        $later = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $revised = app(ContributionDispatcher::class)->dispatch($participant, $later, [
            'action' => 'proposition.revise', 'proposition_id' => $write->id, 'statement' => 'Corrected Variant',
        ]);
        $this->receiptFixture($revised);
        $return = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $this->assertUnavailable($participant, $return, $input);
        $plan = $this->plan($participant);
        $this->assertContains($revised->id, $this->targets($plan, 'attestation.create'));
        $this->assertContains($revised->id, $this->targets($plan, 'instrument.execute'));
        $this->assertSame([], $this->targets($plan, 'proposition.revise'));
        $this->assertFalse($plan['tragedy']);
    }

    public function test_persist_variant_quota_cannot_be_reset_by_putting_the_gate_in_another_domain(): void
    {
        [$participant, $seed] = $this->fixture(true);
        $gate = $this->node($seed->phase, 'activity', 'Another Participant’s Gate', parent: $seed);
        $phase = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $input = ['action' => 'proposition.variant', 'statement' => 'First Variant'];
        app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
        try {
            $this->phase($participant, PhaseKind::Persist, 'stance', $gate);
            $this->fail('An expressive Gate cannot be moved into the opposite Domain.');
        } catch (DomainException) {
            $this->assertSame('activity', $gate->fresh()->register);
        }
        $later = $this->phase($participant, PhaseKind::Persist, 'frontier', $gate);
        $this->assertUnavailable($participant, $later, $input);
    }

    #[DataProvider('localPhases')]
    public function test_generation_is_personal_and_survives_cycles_but_not_a_different_gate(PhaseKind $kind, string $register, string $action): void
    {
        [$participant, $gate] = $this->fixture($kind === PhaseKind::Persist && $action === 'proposition.variant');
        if ($action === 'proposition.reclassify') {
            $gate = $this->node($gate->phase, $kind === PhaseKind::Persist ? 'activity' : 'frontier', 'Expressive Gate', parent: $gate);
        }
        $phase = $this->phase($participant, $kind, $register, $gate);
        if ($kind === PhaseKind::Constrain) {
            $this->loadedReceiptFixture($gate);
        }
        if ($action === 'proposition.reclassify') {
            $this->classificationEvidenceFixture($gate);
        }
        $input = $action === 'proposition.reclassify' ? ['action' => $action] : ['action' => $action, 'statement' => 'First Write'];
        app(ContributionService::class)->validate($participant, $input);
        app(ParticipationService::class)->contribute($participant, $phase->id, $input);
        $written = Proposition::findOrFail($participant->fresh()->current_proposition_id);
        $this->receiptFixture($written);
        $return = $this->phase($participant, $kind, $register, $gate);
        $this->assertUnavailable($participant, $return, $input);
        $plan = $this->plan($participant);
        $this->assertArrayNotHasKey('proposition.variant', $plan['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('proposition.reclassify', $plan['schema']['$defs']['actions']);
        $this->assertContains($written->id, $this->targets($plan, 'attestation.create'));
        app(ParticipationService::class)->contribute($participant, $return->id,
            ['action' => 'attestation.create', 'proposition_id' => $written->id, 'direction' => 'support']);

        $other = $this->participant($participant->substrate, 'other');
        $otherPhase = $this->phase($other, $kind, $register, $gate);
        if ($kind !== PhaseKind::Constrain) {
            $otherInput = ['action' => 'proposition.variant', 'statement' => 'Another Participant'];
            app(ContributionService::class)->validate($other, $otherInput);
            $otherWrite = app(ContributionDispatcher::class)->dispatch($other, $otherPhase, $otherInput);
            $this->receiptFixture($otherWrite);
            $this->phase($participant, $kind, $register, $gate);
            $this->assertContains($otherWrite->id, $this->targets($this->plan($participant), 'attestation.create'));
        }
        // A new local Gate in the same substrate/Horizon is a fresh opportunity.
        $newGate = $this->node($gate->phase, $gate->register, 'Another Gate');
        $this->phase($participant, $kind, $register, $newGate);
        if ($kind === PhaseKind::Constrain) {
            $this->loadedReceiptFixture($newGate);
        }
        if ($action === 'proposition.reclassify') {
            $this->classificationEvidenceFixture($newGate);
        }
        app(ContributionService::class)->validate($participant, $input);
    }

    public function test_disperse_revision_stays_in_write_and_does_not_reset_generation(): void
    {
        [$participant, $gate] = $this->fixture();
        $phase = $this->phase($participant, PhaseKind::Disperse, 'activity', $gate);
        $input = ['action' => 'proposition.variant', 'statement' => 'First Variant'];
        $first = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
        foreach (['Second draft', 'Third draft'] as $statement) {
            $this->receiptFixture($first);
            $refutePhase = $this->phase($participant, PhaseKind::Disperse, 'activity', $gate);
            $this->assertSame([], $this->targets($this->plan($participant), 'proposition.revise'));
            app(ContributionDispatcher::class)->dispatch($participant, $refutePhase, [
                'action' => 'attestation.create', 'proposition_id' => $first->id, 'direction' => 'refute',
            ]);
            $this->assertSame([], $this->targets($this->plan($participant), 'proposition.revise'));
            $return = $this->phase($participant, PhaseKind::Disperse, 'activity', $gate);
            $plan = $this->plan($participant);
            $this->assertSame([$first->id], $this->targets($plan, 'proposition.revise'));
            $this->assertNotContains($gate->id, $this->targets($plan, 'instrument.execute'));
            $this->assertUnavailable($participant, $return, $input);
            app(ContributionService::class)->validate($participant, [
                'action' => 'proposition.revise', 'proposition_id' => $first->id, 'statement' => $statement,
            ]);
            $first = app(ContributionDispatcher::class)->dispatch($participant, $return, [
                'action' => 'proposition.revise', 'proposition_id' => $first->id, 'statement' => $statement,
            ]);
        }
        $this->receiptFixture($first);
        $this->phase($participant, PhaseKind::Disperse, 'activity', $gate);
        $plan = $this->plan($participant);
        $this->assertContains($first->id, $this->targets($plan, 'attestation.create'));
        $this->assertContains($first->id, $this->targets($plan, 'instrument.execute'));
    }

    public function test_constrain_requires_receipt_but_not_reinforcement(): void
    {
        [$participant, $gate] = $this->fixture();
        $gate = $this->node($gate->phase, 'frontier', 'Expressive Gate', parent: $gate);
        $phase = $this->phase($participant, PhaseKind::Constrain, 'canon', $gate);
        $input = ['action' => 'proposition.reclassify'];
        $this->assertUnavailable($participant, $phase, $input);
        app(OperationalInstrumentService::class)->execute($phase, $gate, ['kind' => 'citation', 'reference' => $gate->id]);
        $this->assertSame(0, app(ReinforcementService::class)->count($gate));
        $this->assertUnavailable($participant, $phase, $input);
        $this->classificationEvidenceFixture($gate);
        app(ContributionService::class)->validate($participant, $input);
        $written = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
        $this->assertSame('canon', $written->register);
    }

    public function test_bias_is_once_per_participant_region_row_and_existing_writes_survive_alternative_gates(): void
    {
        [$participant, $seed] = $this->fixture();
        $a = $this->node($seed->phase, 'frontier', 'A', parent: $seed);
        $b = $this->node($seed->phase, 'frontier', 'B', parent: $seed);
        $this->classificationEvidenceFixture($a);
        $this->classificationEvidenceFixture($b);
        $phase = $this->phase($participant, PhaseKind::Bias, 'canon', $a);
        $input = ['action' => 'proposition.bias', 'proposition_id' => $a->id];
        app(ContributionService::class)->validate($participant, $input);
        $written = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
        $this->receiptFixture($written);
        $return = $this->phase($participant, PhaseKind::Bias, 'canon', $b);
        $this->assertUnavailable($participant, $return, ['action' => 'proposition.bias', 'proposition_id' => $b->id]);
        $plan = $this->plan($participant);
        $this->assertContains($written->id, $this->targets($plan, 'attestation.create'));
        $this->assertContains($written->id, $this->targets($plan, 'instrument.execute'));
        $other = $this->participant($participant->substrate, 'other');
        $this->phase($other, PhaseKind::Bias, 'canon', $b);
        app(ContributionService::class)->validate($other, ['action' => 'proposition.bias', 'proposition_id' => $b->id]);
    }

    public function test_order_consumes_each_direct_source_globally_and_preserves_other_sources(): void
    {
        [$participant, $seed] = $this->fixture();
        $this->mock(ClosureGateService::class, function ($mock): void {
            $mock->shouldReceive('preview', 'forCommitment')->andReturn([
                ...(new ClosureValues(1, 1))->resolve(),
                'history_proposition_id' => null, 'history_source' => 'negative-default',
            ]);
        });
        $a = $this->node($seed->phase, 'canon', 'A', parent: $seed);
        $b = $this->node($seed->phase, 'canon', 'B', parent: $seed);
        $this->groundFixture($a);
        $this->groundFixture($b);
        $phase = $this->phase($participant, PhaseKind::Order, 'canon', $a);
        $input = ['action' => 'proposition.commit', 'proposition_id' => $a->id];
        app(ContributionService::class)->validate($participant, $input);
        app(ParticipationService::class)->contribute($participant, $phase->id, $input);
        $commit = Proposition::findOrFail($participant->fresh()->current_proposition_id);
        $return = $this->phase($participant, PhaseKind::Order, 'canon', $b);
        // A distinct source remains committable, regardless of who committed its sibling.
        app(ContributionService::class)->validate($participant, ['action' => 'proposition.commit', 'proposition_id' => $b->id]);
        $plan = $this->plan($participant);
        $this->assertTrue($plan['commitment']['admissible']);
        $this->assertNotContains($commit->id, $this->targets($plan, 'attestation.create'));
        $this->assertNotContains($commit->id, $this->targets($plan, 'instrument.execute'));
        $other = $this->participant($participant->substrate, 'other');
        $otherPhase = $this->phase($other, PhaseKind::Order, 'canon', $a);
        $this->assertUnavailable($other, $otherPhase, $input);

        // A new Committal seed creates a new row even within the same Horizon.
        $next = $this->node($seed->phase, 'canon', 'Next region', parent: $commit);
        $this->groundFixture($next);
        $this->phase($participant, PhaseKind::Order, 'canon', $next);
        app(ContributionService::class)->validate($participant, ['action' => 'proposition.commit', 'proposition_id' => $next->id]);
    }

    public function test_dispatcher_rejects_support_on_visible_gate_and_unrelated_write(): void
    {
        [$participant, $gate] = $this->fixture();
        $unrelated = $this->node($gate->phase, 'activity', 'Unrelated Write');
        $phase = $this->phase($participant, PhaseKind::Disperse, 'activity', $gate);
        foreach ([$gate, $unrelated] as $target) {
            foreach (['attestation.create', 'instrument.execute', 'proposition.revise'] as $action) {
                $this->assertUnavailable($participant, $phase, [
                    'action' => $action, 'proposition_id' => $target->id,
                    ...match ($action) {
                        'attestation.create' => ['direction' => 'support'],
                        'instrument.execute' => ['instrument' => ['kind' => 'citation', 'reference' => $gate->id]],
                        default => ['statement' => 'Invalid revision'],
                    },
                ]);
            }
        }
    }

    private function assertUnavailable(Participant $participant, Phase $phase, array $input): void
    {
        $counts = [Proposition::count(), Supersession::count(), $participant->substrate->ledgerEntries()->count()];
        try {
            app(ContributionService::class)->validate($participant, $input);
            $this->fail('The packet must exclude the invalid action.');
        } catch (InvalidArgumentException) {
        }
        try {
            app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
            $this->fail('Direct dispatch must reject the invalid action.');
        } catch (DomainException) {
        }
        $this->assertSame($counts, [Proposition::count(), Supersession::count(), $participant->substrate->ledgerEntries()->count()]);
    }

    private function fixture(bool $foreignGate = false): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'write-once', 'title' => 'Write Once', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = $this->participant($substrate, 'author');
        $owner = $foreignGate ? $this->participant($substrate, 'gate-owner') : $participant;
        $history = $this->phase($owner, PhaseKind::Distinguish, 'activity');
        $history->forceFill(['completed_at' => now()])->save();
        $history->cycle->forceFill(['completed_at' => now()])->save();

        return [$participant, $this->node($history, 'canon', 'Seed', tier: 'committal')];
    }

    private function participant(Substrate $substrate, string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign])->save();

        return $participant;
    }

    private function phase(Participant $participant, PhaseKind $kind, string $register, ?Proposition $gate = null): Phase
    {
        $participant->cycles()->whereNull('completed_at')->each(function ($cycle): void {
            $cycle->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
            $cycle->forceFill(['completed_at' => now()])->save();
        });
        $horizon = $this->horizonForRegisterFixture($participant, $register);
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register,
            'gate_proposition_id' => $gate?->id, 'started_at' => now()])->save();

        return $phase;
    }

    private function node(Phase $phase, string $register, string $statement, string $tier = 'expressive', ?Proposition $parent = null): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => $tier, 'statement' => $statement])->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => 'variant'])->save();
            app(LedgerService::class)->record($edge);
        }

        return $node;
    }

    private function plan(Participant $participant): array
    {
        return app(InputPlanService::class)->forParticipant($participant);
    }

    private function targets(array $plan, string $action): array
    {
        return $plan['schema']['$defs']['actions'][$action]['properties']['proposition_id']['enum'] ?? [];
    }
}
