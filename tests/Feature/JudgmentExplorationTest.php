<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\ParticipantExplorationService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ExplorationHistory;
use Tests\Fixtures\ParticipantExplorationFixture;
use Tests\TestCase;

class JudgmentExplorationTest extends TestCase
{
    use ParticipantExplorationFixture, RefreshDatabase;

    public function test_judgment_precedes_rest_and_remains_personal_after_new_growth(): void
    {
        [$signal, $persist, $constrain, $write, $mediate] = $this->personalJourney(omitJudgment: 4);
        $exploration = app(ParticipantExplorationService::class);
        self::assertTrue($exploration->explored($signal, $mediate));
        self::assertFalse($exploration->explored($write, $mediate), 'Authorship is independent of judgment.');
        $entry = $this->returnAfterRest($mediate);
        self::assertFalse($exploration->explored($write, $entry));
        $other = $this->phase($constrain, PhaseKind::Disperse);
        $this->personalVote($other, $write);
        self::assertTrue($exploration->explored($write, $other));
        self::assertFalse($exploration->explored($write, $entry));
        $actor = $entry->cycle->participant;
        $this->publishPotentialFixture($this->substrate);
        $phase = $entry;
        $service = app(ParticipationService::class);
        foreach ([$signal, $persist, $constrain] as $gate) {
            $state = $service->contribute($actor, $phase->id, ['action' => 'proposition.traverse', 'proposition_id' => $gate->id]);
            $phase = Phase::findOrFail($state['plan']['phase']['id']);
        }
        $actions = $state['plan']['schema']['$defs']['actions'];
        self::assertContains($write->id, $actions['attestation.create']['properties']['proposition_id']['enum']);
        self::assertArrayNotHasKey('participant.rest', $actions);
        $service->contribute($actor, $phase->id, ['action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'support']);
        self::assertTrue($exploration->explored($write, $phase));
    }

    public function test_rest_at_a_judged_reconciliation_does_not_hide_its_unjudged_disperse_operand(): void
    {
        [, , $constrain, $operand, $mediate] = $this->personalJourney(omitJudgment: 4);
        $siblingBirth = $this->phase($constrain, PhaseKind::Disperse);
        $other = $this->node($siblingBirth, $constrain);
        foreach ([$other, $operand] as $gate) {
            app(PropositionMeasurementService::class)->project($gate, .8, .8, .8, correspondenceComponents: new CorrespondenceValues(.8, .8));
        }
        $producer = app(PhaseProgressionService::class)->advance($siblingBirth, $other);
        $result = app(ContributionDispatcher::class)->dispatch($producer->cycle->participant, $producer, [
            'action' => 'proposition.reconcile', 'other_proposition_id' => $operand->id, 'statement' => 'Both observations describe the same demand.',
        ]);
        // The historical witness and personal vote belong to the route through the other operand.
        $rest = ExplorationHistory::record($result, $mediate);
        $arrival = DB::table('exploration_events')->where('phase_id', $rest)->value('arrival_phase_id');
        $this->personalVote(Phase::findOrFail($arrival), $result);
        $next = $operand->phase->replicate();
        $next->completed_at = null;
        $phase = $this->governedPhaseFixture($next, $this->origin, $constrain);
        $ctx = new ParticipationContext($phase);
        $options = app(PropositionOptionService::class);
        self::assertTrue($options->writeOptionsFor($phase, $ctx)->contains('id', $operand->id));
        self::assertFalse($options->traversalOptionsFor($phase, $ctx, base: true)->contains('id', $operand->id));
        self::assertTrue($options->attestationOptionsFor($phase, $ctx)->contains('id', $operand->id));
    }

    public function test_prior_judgment_does_not_require_another_visit_to_an_empty_mediate_frontier(): void
    {
        [$signal, $persist, $constrain, $head, $mediate] = $this->personalJourney(omitJudgment: 1);
        // No historical Rest witness. A prior-Cycle vote is sufficient base movement authority.
        $phase = $this->returnAfterRest($mediate);
        $this->assertDatabaseCount('exploration_events', 0);
        foreach ([$signal, $persist, $constrain] as $gate) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $gate);
        }
        $options = app(PropositionOptionService::class);
        self::assertTrue($options->traversalOptionsFor($phase, base: true)->contains('id', $head->id));
        self::assertFalse($options->traversalOptionsFor($phase)->contains('id', $head->id));
        $child = $this->node($this->phase($head, PhaseKind::Mediate), $head);
        self::assertTrue($options->traversalOptionsFor($phase)->contains('id', $head->id));
        self::assertTrue(app(ParticipantExplorationService::class)->explored($head, $phase));
        self::assertFalse(app(ParticipantExplorationService::class)->explored($child, $phase));
    }

    public function test_a_known_judgment_outside_the_current_horizon_does_not_grant_movement(): void
    {
        [$signal, , , $head] = $this->personalJourney();
        $entry = $this->commitFrom($signal, boundary: true);
        $distinguish = $signal->phase->replicate();
        $distinguish->write_register = app(ContextHorizonProjector::class)->forEntry($entry)->domain->registerFor(PhaseKind::Distinguish);
        $phase = $this->governedPhaseFixture($distinguish, $entry, $entry);
        $context = new ParticipationContext($phase);
        self::assertFalse($context->propositions->has($head->id));
        self::assertTrue(app(ParticipantExplorationService::class)->explored($head, $phase, $context));
        self::assertFalse(app(PropositionOptionService::class)->traversalOptionsFor($phase, $context)->contains('id', $head->id));
    }

    public function test_field_change_between_a_rest_plan_and_submission_reveals_the_new_first_judgment(): void
    {
        [, , , $gate, $mediate] = $this->personalJourney();
        $participant = $mediate->cycle->participant;
        $this->publishPotentialFixture($this->substrate);
        $service = app(ParticipationService::class);
        $old = $service->stateFor($participant);
        self::assertArrayHasKey('participant.rest', $old['plan']['schema']['$defs']['actions']);
        $child = $this->node($this->phase($gate, PhaseKind::Mediate), $gate);
        $this->publishPotentialFixture($this->substrate);
        $ledger = DB::table('substrate_ledger_entries')->count();
        try {
            $service->contribute($participant, $mediate->id, ['action' => 'participant.rest'], $old['plan']['schema']);
            self::fail('A stale Rest concealed a new first judgment.');
        } catch (ConflictHttpException) {
            self::assertNull($mediate->fresh()->completed_at);
            self::assertNull($mediate->cycle->fresh()->rested_at);
            self::assertSame($ledger, DB::table('substrate_ledger_entries')->count());
            $this->assertDatabaseCount('exploration_events', 0);
        }
        $actions = $service->stateFor($participant)['plan']['schema']['$defs']['actions'];
        self::assertContains($child->id, $actions['attestation.create']['properties']['proposition_id']['enum']);
        self::assertArrayNotHasKey('participant.rest', $actions);
    }

    public function test_completed_frontier_recovers_without_a_repeat_traverse(): void
    {
        [$signal, $persist, $constrain, $gate, $mediate] = $this->personalJourney(omitJudgment: 1, omitAcquisition: [1, 2, 4]);
        $stance = $this->node($mediate, $gate);
        $this->personalVote($mediate, $stance, 'support');
        $bias = app(PhaseProgressionService::class)->advance($mediate, $stance);
        $this->publishPotentialFixture($this->substrate);
        $phase = $this->returnAfterRest($bias);
        foreach ([$signal, $persist, $constrain, $gate] as $arrival) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $arrival);
        }

        $actor = $phase->cycle->participant;
        $service = app(ParticipationService::class);
        $actions = [];
        $this->publishPotentialFixture($this->substrate);
        $plan = app(InputPlanService::class)->forPhase($actor, $phase);
        if (in_array($stance->id, $plan['schema']['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum'] ?? [], true)) {
            $actions[] = 'proposition.traverse';
            $state = $service->contribute($actor, $phase->id, ['action' => 'proposition.traverse', 'proposition_id' => $stance->id]);
            $plan = $state['plan'];
        }
        self::assertArrayHasKey('participant.rest', $plan['schema']['$defs']['actions']);
        $actions[] = 'participant.rest';
        $service->contribute($actor, $plan['phase']['id'], ['action' => 'participant.rest']);
        self::assertSame($this->origin->id, $actor->cycles()->latest('sequence')->value('entry_committal_id'));
        self::assertSame(1, $stance->effectiveAttestations()->count());
        $this->assertDatabaseCount('exploration_events', 0);
        self::assertSame(['participant.rest'], $actions);
    }
}
