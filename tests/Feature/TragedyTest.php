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
use App\Services\LedgerService;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\TestCase;

class TragedyTest extends TestCase
{
    use RefreshDatabase;

    private ?Proposition $fixtureGovernor = null;

    public static function phaseActions(): array
    {
        return [
            'Distinguish Variant without traversal' => [PhaseKind::Distinguish, 'proposition.variant'],
            'Persist Variant without traversal' => [PhaseKind::Persist, 'proposition.variant'],
            'Persist Instrument without traversal' => [PhaseKind::Persist, 'instrument.execute'],
            'Disperse first Variant' => [PhaseKind::Disperse, 'proposition.variant'],
            'Constrain owned Gate Reclassify' => [PhaseKind::Constrain, 'proposition.reclassify'],
            'Constrain owned descendant Revise' => [PhaseKind::Constrain, 'proposition.revise'],
            'Constrain untouched descendant Attestation' => [PhaseKind::Constrain, 'attestation.create'],
            'Constrain attested descendant Traverse' => [PhaseKind::Constrain, 'proposition.traverse'],
            'Mediate counterpart outside Write' => [PhaseKind::Mediate, 'proposition.reconcile'],
            'Bias Instrument without traversal' => [PhaseKind::Bias, 'instrument.execute'],
            'Constrain exhausted non-owner' => [PhaseKind::Constrain, null],
            'Mediate no counterpart or continuation' => [PhaseKind::Mediate, null],
            'Bias no counterpart instrument or continuation' => [PhaseKind::Bias, null],
            'Order inadmissible Commit with no continuation' => [PhaseKind::Order, null],
        ];
    }

    #[DataProvider('phaseActions')]
    public function test_rest_is_exclusive_to_complete_action_exhaustion(PhaseKind $kind, ?string $action): void
    {
        [$participant, $phase] = $this->fixture($kind, $action);
        $service = app(ParticipationService::class);
        $this->publishPotentialFixture($participant);
        $state = $service->stateFor($participant);
        $plan = $state['plan'];
        $schema = $plan['schema'];
        $actions = array_keys($schema['$defs']['actions']);
        $this->assertSame($action === null, $plan['rest_available']);
        $this->assertSame($actions, array_map(fn ($ref) => basename($ref['$ref']), $schema['oneOf']));
        if ($action === null) {
            $this->assertSame(['participant.rest'], $actions);
        } else {
            $this->assertContains($action, $actions);
            $this->assertNotContains('participant.rest', $actions);
            $this->assertStringNotContainsString('participant.rest', json_encode($schema));
        }
        if (! in_array($action, ['proposition.traverse', 'proposition.revise'], true)) {
            $this->assertSame([], $plan['schema']['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum'] ?? []);
        }

        // Autonomous inference receives the same action set through the autonomous context.
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($schema, $prompt['schema']);
        $this->assertSame($plan['rest_available'], json_decode($prompt['user'], true)['plan']['rest_available']);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
        $contribution = $transport['properties']['contribution'];
        $variants = $contribution['anyOf'] ?? [$contribution];
        $inferenceActions = array_values(array_unique(array_merge(...array_map(
            fn ($variant) => $variant['properties']['action']['enum'], $variants,
        ))));
        $this->assertEqualsCanonicalizing($actions, $inferenceActions);
        $rest = ['action' => 'participant.rest'];
        $validator = app(Validator::class);
        $this->assertSame($action === null, $validator->validate(Helper::toJSON($rest), Helper::toJSON($schema))->isValid());
        $transportRest = array_replace(array_fill_keys($variants[0]['required'], null), $rest);
        $this->assertSame($action === null, $validator->validate(
            Helper::toJSON(['contribution' => $transportRest]), Helper::toJSON($transport),
        )->isValid());

        $propositions = Proposition::count();
        $supersessions = Supersession::count();
        if ($action === null) {
            $this->publishPotentialFixture($participant);
            $after = $service->contribute($participant, $phase->id, $rest);
            $this->assertSame('waiting', $after['status']);
            $this->assertNotNull($phase->fresh()->completed_at);
            $this->assertNotNull($phase->cycle->fresh()->rested_at);
        } else {
            try {
                $this->publishPotentialFixture($participant);
                $service->contribute($participant, $phase->id, $rest);
                $this->fail('Rest cannot compete with an admissible action.');
            } catch (InvalidArgumentException) {
                $this->assertNull($phase->fresh()->completed_at);
                $this->assertNull($phase->cycle->fresh()->rested_at);
            }
        }
        $this->assertSame($phase->gate_proposition_id, $participant->fresh()->current_proposition_id);
        $this->assertSame($propositions, Proposition::count());
        $this->assertSame($supersessions, Supersession::count());
    }

    private function fixture(PhaseKind $kind, ?string $action): array
    {
        if (($kind === PhaseKind::Constrain && $action === 'proposition.reclassify')
            || ($kind === PhaseKind::Disperse && $action === 'proposition.variant')) {
            $fixture = new GovernedExpressionFixture;
            $fixture->advanceToConstrain();
            if ($kind === PhaseKind::Disperse) {
                $receipt = app(\App\Services\PropositionStateService::class)->receipts($fixture->expression)->sole();
                $result = app(ContributionDispatcher::class)->dispatch($fixture->actor, $fixture->phase,
                    ['action' => 'proposition.reclassify', 'proposition_id' => $fixture->expression->id, 'receipt_id' => $receipt->id]);
                $fixture->phase = app(PhaseProgressionService::class)->advance($fixture->phase, $result);
            }

            return [$fixture->actor->fresh(), $fixture->phase];
        }
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'rest_available', 'title' => 'Tragedy', 'visibility' => 'public', 'configuration' => []])->save();
        $author = $this->participant($substrate, 'author');
        $viewer = $this->participant($substrate, 'viewer');
        // These two action-exhaustion cases need only real bootstrap/development,
        // not an ordinary Committal manufactured by changing an expressive tier.
        if ($action === 'proposition.variant' && in_array($kind, [PhaseKind::Distinguish, PhaseKind::Persist], true)) {
            $service = app(ParticipationService::class);
            $state = $service->enter($viewer, ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
            if ($kind === PhaseKind::Persist) {
                $state = $service->contribute($viewer, $state['plan']['phase']['id'],
                    ['action' => 'proposition.variant', 'statement' => 'First distinction']);
            }

            return [$viewer->fresh(), Phase::findOrFail($state['plan']['phase']['id'])];
        }
        $authorPhase = $this->phase($author, PhaseKind::Distinguish, 'activity', true);
        $history = $this->phase($viewer, PhaseKind::Distinguish, 'activity', true);
        if (in_array($action, ['proposition.reclassify', 'proposition.reconcile'], true)) {
            $seed = $this->proposition($authorPhase, 'canon');
            $seed->forceFill(['tier' => 'committal'])->save();
            $this->freezeRegionFixture($seed);
            $this->fixtureGovernor = $seed;
            $authorPhase = $this->governedPhaseFixture($authorPhase, $seed);
            $history = $this->governedPhaseFixture($history, $seed);
        }
        $writeRegister = match ($kind) {
            PhaseKind::Distinguish, PhaseKind::Disperse => 'activity',
            PhaseKind::Persist, PhaseKind::Mediate => 'frontier',
            default => 'canon',
        };
        $gateKind = $kind === PhaseKind::Distinguish ? PhaseKind::Order : PhaseKind::from($kind->value - 1);
        $gatePhase = $this->phaseAtPositionFixture($action === 'proposition.reclassify' ? $history : $authorPhase, $gateKind);
        $gate = $this->proposition($gatePhase, $gatePhase->write_register);
        if ($kind === PhaseKind::Distinguish) {
            $gate->forceFill(['tier' => 'committal'])->save();
        }
        $viewer->current_proposition_id = $gate->id;
        $viewer->save();
        if ($kind === PhaseKind::Mediate) {
            $parent = $this->proposition($this->phaseAtPositionFixture($authorPhase, PhaseKind::Constrain), 'canon');
            $seed ??= $this->proposition($authorPhase, 'canon');
            $seed->forceFill(['tier' => 'committal'])->save();
            $seed->children()->attach($parent->id, [
                'id' => (string) str()->ulid(), 'phase_id' => $authorPhase->id, 'type' => 'variant',
            ]);
            $parent->children()->attach($gate->id, [
                'id' => (string) str()->ulid(), 'phase_id' => $authorPhase->id, 'type' => 'variant',
            ]);
            $disperse = $this->phase($viewer, PhaseKind::Disperse, 'activity');
            $disperse->gate_proposition_id = $parent->id;
            $disperse->save();
            $phase = app(PhaseProgressionService::class)->advance($disperse, $gate);
        } else {
            $phase = $this->phase($viewer, $kind, $writeRegister);
        }
        $phase->gate_proposition_id = $gate->id;
        $phase->save();
        if ($action === 'proposition.reclassify') {
            $seed ??= $this->proposition($authorPhase, 'canon');
            $seed->forceFill(['tier' => 'committal'])->save();
            $seed->children()->attach($gate->id, ['id' => (string) str()->ulid(), 'phase_id' => $gatePhase->id, 'type' => 'variant']);
            $this->classificationEvidenceFixture($gate);
            $this->loadedReceiptFixture($gate);
        }
        if ($action === 'instrument.execute') {
            $written = $this->proposition($this->phaseAtPositionFixture($authorPhase, $kind), $writeRegister);
            $gate->children()->attach($written->id, [
                'id' => (string) str()->ulid(), 'phase_id' => $authorPhase->id, 'type' => 'variant',
            ]);
        }
        if (in_array($action, ['proposition.revise', 'attestation.create', 'proposition.traverse', 'proposition.reconcile'], true)) {
            $target = $this->proposition(
                $this->phaseAtPositionFixture($action === 'proposition.revise' ? $history : $authorPhase,
                    $action === 'proposition.reconcile' ? PhaseKind::Disperse : $kind),
                $action === 'proposition.reconcile' ? 'activity' : $writeRegister,
            );
            if ($action === 'proposition.reconcile') {
                $parent ??= $this->proposition($authorPhase, 'canon');
                foreach ([$target] as $sibling) {
                    $parent->children()->attach($sibling->id, [
                        'id' => (string) str()->ulid(), 'phase_id' => $authorPhase->id, 'type' => 'variant',
                    ]);
                }
            }
            $edge = new Supersession;
            if ($action !== 'proposition.reconcile') {
                $edge->forceFill(['phase_id' => $authorPhase->id, 'source_proposition_id' => $gate->id, 'target_proposition_id' => $target->id, 'type' => 'variant'])->save();
            }
            if (in_array($action, ['proposition.traverse', 'proposition.revise'], true)) {
                $attestation = new Attestation;
                $attestation->forceFill(['phase_id' => $history->id, 'proposition_id' => $target->id, 'direction' => $action === 'proposition.revise' ? 'refute' : 'support'])->save();
            }
        }

        return [$viewer, $phase];
    }

    private function participant(Substrate $substrate, string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign, 'max_read_count' => 25])->save();

        return $participant;
    }

    private function phase(Participant $participant, PhaseKind $kind, string $register, bool $completed = false): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1, 'completed_at' => $completed ? now() : null,
            'entry_committal_id' => $this->fixtureGovernor?->id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register, 'started_at' => now(), 'completed_at' => $completed ? now() : null])->save();

        return $phase;
    }

    private function proposition(Phase $phase, string $register): Proposition
    {
        $proposition = new Proposition;
        $proposition->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => 'expressive', 'statement' => 'Local proposition'])->save();
        app(LedgerService::class)->record($proposition);

        $this->receiptFixture($proposition);

        return $proposition;
    }
}

