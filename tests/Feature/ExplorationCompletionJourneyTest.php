<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\AcquisitionAttemptService;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use App\Services\HomologousBiasService;
use App\Services\InputPlanService;
use App\Services\MediatedIncomingBiasService;
use App\Services\ParticipantExplorationService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PhaseService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\Fixtures\ParticipantExplorationFixture;
use Tests\TestCase;

/** One Participant's route. Fixture measurements isolate eligibility at the compared Gates. */
#[Group('integration')]
class ExplorationCompletionJourneyTest extends TestCase
{
    use IsolatesClosureOutput, ParticipantExplorationFixture, RefreshDatabase;

    public function test_terminal_constrain_to_a_reopened_bias_head_and_a_productive_mediate_return(): void
    {
        $this->resolveExpressiveFieldFixture(.8);
        $this->initializeExhaustionFixture(originOpensSettlement: true);
        $distinguish = $this->phase($this->origin, PhaseKind::Distinguish);
        $actor = $distinguish->cycle->participant;
        $signal = $this->node($distinguish, $this->origin);

        $persist = app(PhaseProgressionService::class)->advance($distinguish, $signal);
        $head = $this->node($persist, $signal);
        $sibling = $this->node($this->phase($signal, PhaseKind::Persist), $signal);
        $this->publishPotentialFixture($this->substrate);
        $firstArrival = $this->judge($persist, $head);
        $next = $this->returnAfterRest($firstArrival);
        $this->assertDatabaseCount('exploration_events', 0);
        $persist = $this->judge($next, $signal);
        $actions = app(InputPlanService::class)->forPhase($actor, $persist)['schema']['$defs']['actions'];
        self::assertNotContains($head->id, $actions['proposition.traverse']['properties']['proposition_id']['enum'] ?? []);
        self::assertContains($sibling->id, $actions['attestation.create']['properties']['proposition_id']['enum']);
        self::assertArrayNotHasKey('instrument.execute', $actions);
        $constrain = $this->judge($persist, $sibling);
        self::assertSame($sibling->id, $constrain->gate_proposition_id);
        self::assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($constrain));
        $next = $this->returnAfterRest($constrain);
        $this->assertDatabaseHas('exploration_events', ['phase_id' => $constrain->id, 'proposition_id' => $sibling->id]);
        $persist = $this->traverse($next, $signal);
        $actions = app(InputPlanService::class)->forPhase($actor, $persist)['schema']['$defs']['actions'];
        self::assertArrayHasKey('instrument.execute', $actions);
        // Resolve the fresh opportunity by Defer. Its ordinary observation reopens Constrain.
        $this->submit($persist, ['action' => 'instrument.execute', 'proposition_id' => $signal->id,
            'instrument' => ['kind' => 'citation', 'reference' => $this->origin->id]]);
        $execution = app(AcquisitionAttemptService::class)->execution($persist);
        $next = $this->submit($persist, ['action' => 'acquisition.defer', 'execution_id' => $execution->id, 'reason' => 'Retain this observation.']);
        $other = $this->phase($head, PhaseKind::Constrain);
        $classification = app(ContributionDispatcher::class)->dispatch($other->cycle->participant, $other, [
            'action' => 'proposition.reclassify', 'proposition_id' => $head->id, 'statement' => $head->statement, 'receipt_id' => $execution->receipt->id]);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertSame([$classification->id], $exploration->heads($head->id, $next, new ParticipationContext($next))->keys()->all());
        $this->assertTrue($exploration->explored($head, $next));
        $this->publishPotentialFixture($this->substrate);
        $constrain = $this->traverse($next, $signal, $head);
        $disperse = $this->judge($constrain, $classification);
        // The new route already has two Disperse siblings. Follow a judged branch to its Mediate leaf.
        $left = $this->node($this->phase($classification, PhaseKind::Disperse), $classification);
        $right = $this->node($this->phase($classification, PhaseKind::Disperse), $classification);
        foreach ([$left, $right] as $operand) {
            app(PropositionMeasurementService::class)->project($operand, .8, .8, .8, correspondenceComponents: new CorrespondenceValues(.8, .8));
        }
        $producer = app(PhaseProgressionService::class)->advance($left->phase, $left);
        $synthesis = app(ContributionDispatcher::class)->dispatch($producer->cycle->participant, $producer,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $right->id, 'statement' => 'The observations share a common cause.']);
        $this->publishPotentialFixture($this->substrate);
        $mediate = $this->judge($disperse, $left);
        $bias = $this->judge($mediate, $synthesis);
        $return = $this->returnAfterRest($bias);
        $this->assertDatabaseHas('exploration_events', ['proposition_id' => $synthesis->id, 'phase_id' => $bias->id]);
        $this->assertTrue($exploration->explored($synthesis, $return));
        // A disjoint pair establishes a second local Bias Gate after the first pair is bound.
        $third = $this->node($this->phase($classification, PhaseKind::Disperse), $classification);
        $fourth = $this->node($this->phase($classification, PhaseKind::Disperse), $classification);
        foreach ([$third, $fourth] as $operand) {
            app(PropositionMeasurementService::class)->project($operand, .8, .8, .8, correspondenceComponents: new CorrespondenceValues(.8, .8));
        }
        $secondProducer = app(PhaseProgressionService::class)->advance($fourth->phase, $fourth);
        $alternate = app(ContributionDispatcher::class)->dispatch($secondProducer->cycle->participant, $secondProducer,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $third->id, 'statement' => 'The observations share a common cause.']);
        $biasProducer = app(PhaseProgressionService::class)->advance($secondProducer, $alternate);
        $this->personalVote($secondProducer, $alternate, 'support');
        $this->coherentChiralityFixture($synthesis);
        $this->coherentChiralityFixture($alternate);
        // This control needs an exhausted Order head. Its fixed Closure input isolates routing.
        $this->isolateClosureOutput();
        $child = app(ContributionDispatcher::class)->dispatch($biasProducer->cycle->participant, $biasProducer,
            ['action' => 'proposition.reclassify', 'proposition_id' => $synthesis->id, 'statement' => $synthesis->statement, 'receipt_id' => $this->acquireObservation()->id]);
        $this->componentClosureInput($child, ['admissible' => false, 'cadence_direction' => 'positive']);
        $this->assertSame([$child->id], $exploration->heads($synthesis->id, $return, new ParticipationContext($return))->keys()->all());
        $this->assertTrue($exploration->explored($synthesis, $return));
        $this->publishPotentialFixture($this->substrate);
        $path = [$signal, $head, $classification, $left];
        $mediate = $this->traverse($return, ...$path);
        $bias = $this->traverse($mediate, $synthesis);
        $order = $this->judge($bias, $child);
        $this->assertSame($child->id, $order->gate_proposition_id);
        $closed = $this->returnAfterRest($order);
        $this->assertDatabaseHas('exploration_events', ['phase_id' => $order->id, 'proposition_id' => $child->id]);
        $this->assertTrue($exploration->explored($synthesis, $closed));
        $disperse = $this->traverse($closed, $signal, $head, $classification);
        $options = app(PropositionOptionService::class);
        $this->assertFalse($options->traversalOptionsFor($disperse)->contains('id', $left->id));
        // Fresh evidence makes a downstream Bias action possible and reopens this route.
        $this->acquireObservation();
        $this->publishPotentialFixture($this->substrate);
        $lastMediate = $this->traverse($disperse, $left);
        $this->assertTrue($exploration->explored($synthesis, $lastMediate));
        $this->assertTrue($options->traversalOptionsFor($lastMediate)->contains('id', $synthesis->id));
        $liveBias = $this->traverse($lastMediate, $synthesis);
        $this->assertTrue($options->gateOptionsFor($liveBias)->contains('id', $alternate->id));
        $this->assertTrue(app(HomologousBiasService::class)->forPhase($liveBias)->contains('id', $alternate->id));
        $this->assertFalse(app(MediatedIncomingBiasService::class)->populationFor($liveBias)->contains('id', $alternate->id));
    }

    /** Other Participants acquire at independent fresh Persist Gates in this Region. */
    private function acquireObservation(): Receipt
    {
        $freshGate = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $probe = $this->phase($freshGate, PhaseKind::Persist);
        $this->publishPotentialFixture($this->substrate);
        $this->submit($probe, ['action' => 'instrument.execute', 'proposition_id' => $freshGate->id,
            'instrument' => ['kind' => 'citation', 'reference' => $freshGate->id]]);
        $attempt = app(AcquisitionAttemptService::class)->execution($probe);
        $this->submit($probe, ['action' => 'acquisition.defer', 'execution_id' => $attempt->id, 'reason' => 'Retain the observation.']);

        return $attempt->receipt;
    }

    private function submit(Phase $phase, array $input): Phase
    {
        $actor = $phase->cycle->participant;
        app(ParticipationService::class)->contribute($actor, $phase->id, $input);

        return app(PhaseService::class)->currentFor($actor->fresh());
    }

    private function judge(Phase $phase, Proposition $write): Phase
    {
        return $this->submit($phase, ['action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'support']);
    }

    private function traverse(Phase $phase, Proposition ...$writes): Phase
    {
        foreach ($writes as $write) {
            $phase = $this->submit($phase, ['action' => 'proposition.traverse', 'proposition_id' => $write->id]);
        }

        return $phase;
    }
}
