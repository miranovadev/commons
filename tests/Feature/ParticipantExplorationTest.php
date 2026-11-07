<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Services\AcquisitionAttemptService;
use App\Services\ClosureOptionService;
use App\Services\ContributionDispatcher;
use App\Services\CorrespondenceGateService;
use App\Services\EvidenceExecutionService;
use App\Services\ExplorationEventService;
use App\Services\HomologousMediationService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\MediationAnchorService;
use App\Services\MediationReadService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantExplorationService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PhaseProgressionService;
use App\Services\PhaseService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use App\Services\ReconciliationOptionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ExplorationHistory;
use Tests\Fixtures\ParticipantExplorationFixture;
use Tests\TestCase;

class ParticipantExplorationTest extends TestCase
{
    use ParticipantExplorationFixture, RefreshDatabase;

    /** A later visit settles the two judgments reopened by observations made after arrival. */
    private function settledJourney(string $direction = 'support'): array
    {
        [$signal, $persist, $constrain, $head, $terminal] = $this->personalJourney($direction);
        $phase = $this->returnAfterRest($terminal);
        foreach ([$signal, $persist, $constrain, $head] as $node) {
            if (! app(ParticipantExplorationService::class)->explored($node, $phase)) {
                $this->personalVote($phase, $node, $node->is($head) ? $direction : 'support');
            }
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }

        $this->publishPotentialFixture($this->substrate);

        return [$signal, $persist, $constrain, $head, $phase];
    }

    public function test_constrain_witness_requires_personal_judgments_and_a_finished_encounter(): void
    {
        $this->resolveExpressiveFieldFixture(.8);
        $this->initializeExhaustionFixture(originOpensSettlement: true);
        $first = $this->phase($this->origin, PhaseKind::Distinguish);
        $signal = $this->node($first, $this->origin);
        $advance = app(PhaseProgressionService::class);
        $persist = $advance->advance($first, $signal);
        $head = $this->node($persist, $signal);
        $constrain = $advance->advance($persist, $head);
        $witness = fn () => app(ExplorationEventService::class)->completionWitness($constrain, new ParticipationContext($constrain));
        self::assertNull($witness());
        $this->personalVote($persist, $head);
        self::assertNull($witness(), 'The Distinguish ancestor still needs a judgment.');
        $this->personalVote($first, $signal);
        self::assertNotNull($witness());

        $service = app(EvidenceExecutionService::class);
        $execution = $service->start($persist, $signal, 'citation', $this->origin->id);
        self::assertNull($witness(), 'An observation is still pending.');
        $receipt = $service->complete($execution, $signal);
        self::assertTrue(app(PhaseGenerationService::class)->available($constrain, 'proposition.reclassify'));
        self::assertNull($witness(), 'Reclassification can consume the Receipt.');
        $child = app(ContributionDispatcher::class)->dispatch($first->cycle->participant, $constrain,
            ['action' => 'proposition.reclassify', 'proposition_id' => $head->id, 'statement' => $head->statement, 'receipt_id' => $receipt->id]);
        self::assertNull($witness(), 'The active Constrain Write still needs a judgment.');
        $this->personalVote($constrain, $child);
        $disperse = $advance->advance($constrain, $child);
        self::assertTrue(app(PhaseGenerationService::class)->available($disperse, 'proposition.variant', prepared: true));
        self::assertNull(app(ExplorationEventService::class)->completionWitness($disperse, new ParticipationContext($disperse)));
    }

    public function test_completed_rest_records_one_personal_head_and_preserves_other_participants_actions(): void
    {
        [$signal, , , $head, $terminal] = $this->settledJourney();
        $nova = $terminal->cycle->participant;
        $luna = $this->phase($this->origin, PhaseKind::Distinguish);
        $return = $this->returnAfterRest($terminal);
        $this->assertDatabaseCount('exploration_events', 1);
        $this->assertDatabaseHas('exploration_events', ['phase_id' => $terminal->id, 'proposition_id' => $head->id]);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertTrue($exploration->explored($head, $return));
        $this->assertTrue($exploration->explored($signal, $return));
        $this->assertFalse($exploration->explored($signal, $luna));
        $options = app(PropositionOptionService::class);
        $this->assertContains($signal->id, $options->attestationOptionsFor($luna)->pluck('id'));
        $this->assertNotContains($signal->id, $options->attestationOptionsFor($return)->pluck('id'), 'The personal stance covers all current evidence.');
        $this->assertContains($signal->id, $options->traversalOptionsFor($return, base: true)->pluck('id'), 'Base navigation remains lawful');
        $this->assertContains($signal->id, $options->traversalOptionsFor($return)->pluck('id'), 'The explored Write leads to fresh generation');
        $this->assertTrue($exploration->explored($signal, $return), 'Base Traverse cannot reopen the explored destination');
        $this->assertSame($nova->id, $return->cycle->participant_id);
        $this->assertSame(0, Tragedy::count());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_variant_only_traverse_preserves_exploration_in_its_own_horizon(): void
    {
        [$signal, $persist, $constrain, , $terminal] = $this->settledJourney();
        $phase = $this->returnAfterRest($terminal);
        $actor = $phase->cycle->participant;
        foreach ([$signal, $persist] as $target) {
            app(ParticipationService::class)->contribute($actor, $phase->id, ['action' => 'proposition.traverse', 'proposition_id' => $target->id]);
            $phase = $actor->cycles()->whereNull('completed_at')->sole()->currentPhase;
        }
        $history = DB::table('exploration_events')->get()->toJson();
        $options = app(PropositionOptionService::class);
        self::assertTrue($options->traversalOptionsFor($phase)->contains('id', $constrain->id));
        $foreignEntry = $this->commitFrom($signal, boundary: true);
        $foreignGate = $this->node($this->phase($foreignEntry, PhaseKind::Constrain), $foreignEntry);
        $foreign = $this->phase($foreignGate, PhaseKind::Disperse);
        self::assertNotSame($phase->cycle->context_horizon_id, $foreign->cycle->context_horizon_id);
        self::assertTrue(app(PhaseGenerationService::class)->available($foreign, 'proposition.variant', prepared: true));
        self::assertTrue(app(ParticipantExplorationService::class)->explored($constrain, $phase));
        self::assertTrue($options->traversalOptionsFor($phase, base: true)->contains('id', $constrain->id));
        self::assertTrue($options->traversalOptionsFor($phase)->contains('id', $constrain->id));
        $state = app(ParticipationService::class)->contribute($actor, $phase->id,
            ['action' => 'proposition.traverse', 'proposition_id' => $constrain->id]);
        self::assertSame($constrain->id, $state['current_proposition_id']);
        self::assertArrayHasKey('instrument.execute', $state['plan']['schema']['$defs']['actions']);
        self::assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_an_explored_sibling_does_not_inherit_another_gates_reclassification(): void
    {
        [$signal, $persist, , $firstHead, $terminal] = $this->settledJourney();
        $entry = $this->returnAfterRest($terminal);
        $persistPhase = app(PhaseProgressionService::class)->advance($entry, $signal);
        $sibling = $this->node($persistPhase, $signal);
        $this->personalVote($persistPhase, $sibling);
        $constrain = app(PhaseProgressionService::class)->advance($persistPhase, $sibling);
        $receipt = app(HorizonReceiptService::class)->ordinaryAvailable($constrain)->firstOrFail();
        $classification = app(ContributionDispatcher::class)->dispatch($constrain->cycle->participant, $constrain,
            ['action' => 'proposition.reclassify', 'proposition_id' => $sibling->id, 'statement' => $sibling->statement, 'receipt_id' => $receipt->id]);
        $this->personalVote($constrain, $classification);
        $disperse = app(PhaseProgressionService::class)->advance($constrain, $classification);
        $this->instrumentGateFixture($disperse);
        $secondHead = $this->node($disperse, $classification);
        $this->personalVote($disperse, $secondHead);
        $mediate = app(PhaseProgressionService::class)->advance($disperse, $secondHead);
        $this->measure($secondHead, 0);
        app(PropositionMeasurementService::class)->project($secondHead, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $this->publishPotentialFixture($this->substrate);
        $return = $this->returnAfterRest($mediate);
        $this->assertDatabaseCount('exploration_events', 1);
        self::assertFalse(app(ParticipantExplorationService::class)->explored($classification, $return), 'The Disperse observation follows judgment on its Gate.');
        // Independently learned sibling routes. Only the first Gate still admits Constrain Reclassification.
        foreach ([$firstHead, $secondHead] as $head) {
            app(PropositionMeasurementService::class)->project($head, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(.8, -.8));
        }
        $options = app(PropositionOptionService::class);
        self::assertTrue($options->traversalOptionsFor($return)->contains('id', $signal->id));
        $phase = app(PhaseProgressionService::class)->advance($return, $signal);
        $context = new ParticipationContext($phase);
        $both = collect([$persist->id, $sibling->id]);
        self::assertCount(2, app(ParticipantExplorationService::class)->exploredIds($phase, $both, $context));
        self::assertEqualsCanonicalizing($both->all(), $options->traversalOptionsFor($phase, $context, base: true)->pluck('id')->all());
        self::assertEqualsCanonicalizing($both->all(), $options->traversalOptionsFor($phase, $context)->pluck('id')->all());
        // Both routes can reach fresh generation. Only the first Gate can still be reclassified.
        foreach ([[$persist, true], [$sibling, false]] as [$gate, $canReclassify]) {
            $arrival = (new Phase)->forceFill(['cycle_id' => $phase->cycle_id, 'kind' => PhaseKind::Constrain,
                'write_register' => $phase->cycle->contextHorizon->domain->registerFor(PhaseKind::Constrain),
                'gate_proposition_id' => $gate->id]);
            $arrival->setRelation('cycle', $phase->cycle)->setRelation('gate', $gate);
            self::assertSame($canReclassify, app(PhaseGenerationService::class)->available($arrival, 'proposition.reclassify'));
        }
    }

    public function test_regional_tragedy_withdraws_a_previously_available_variant_route(): void
    {
        [$signal, $persist, $constrain, , $terminal] = $this->settledJourney();
        $phase = $this->returnAfterRest($terminal);
        foreach ([$signal, $persist] as $gate) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $gate);
        }
        $options = app(PropositionOptionService::class);
        self::assertTrue($options->traversalOptionsFor($phase)->contains('id', $constrain->id));
        $this->tragedyBesideFixture($constrain);
        self::assertTrue($options->traversalOptionsFor($phase, base: true)->contains('id', $constrain->id));
        self::assertFalse($options->traversalOptionsFor($phase)->contains('id', $constrain->id));
        $this->assertDatabaseCount('exploration_events', 1);
    }

    public function test_prior_cycle_self_refute_preserves_revision_and_does_not_block_fresh_variant(): void
    {
        [$signal, $persist, $constrain, $head, $terminal] = $this->settledJourney('refute');
        $return = $this->returnAfterRest($terminal);
        $exploration = app(ParticipantExplorationService::class);
        self::assertTrue($exploration->explored($head, $return));
        self::assertTrue($exploration->explored($signal, $return));
        self::assertTrue(app(PhaseGenerationService::class)->available($return, 'proposition.variant', prepared: true));
        $phase = $return;
        foreach ([$signal, $persist, $constrain] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        self::assertArrayHasKey('proposition.revise', $plan['schema']['$defs']['actions']);
        self::assertArrayNotHasKey('attestation.create', $plan['schema']['$defs']['actions']);
        self::assertArrayHasKey('instrument.execute', $plan['schema']['$defs']['actions']);
        $packet = app(ParticipantContextSerializer::class)->inference([
            'participant' => $phase->cycle->participant->only(['id', 'callsign', 'designation']),
            'plan' => app(ParticipantContextSerializer::class)->plan($plan),
        ]);
        $write = collect($packet['write']['propositions'])->firstWhere('id', $head->id);
        self::assertNotNull($write, 'Explored Writes remain visible to autonomous Participants');
        self::assertTrue($write['explored'] ?? false);
        self::assertArrayHasKey('proposition.revise', $packet['actions']['$defs']['actions']);
        self::assertArrayNotHasKey('attestation.create', $packet['actions']['$defs']['actions']);
        self::assertArrayNotHasKey('proposition.traverse', $packet['actions']['$defs']['actions']);
    }

    public function test_rest_is_available_with_an_explored_write(): void
    {
        [, , , $activity, $mediate] = $this->settledJourney();
        $stance = $this->node($mediate, $activity);
        $this->measure($stance, 0);
        $this->personalVote($mediate, $stance);
        // Historical selector input only: this Rest must not invent another encounter.
        ExplorationHistory::record($stance, $mediate);
        $this->publishPotentialFixture($this->substrate);
        $plan = app(InputPlanService::class)->forPhase($mediate->cycle->participant, $mediate);
        self::assertArrayNotHasKey('attestation.create', $plan['schema']['$defs']['actions']);
        self::assertArrayHasKey('participant.rest', $plan['schema']['$defs']['actions']);
        self::assertNull(app(ExplorationEventService::class)->inspect($mediate)['witness']);
        $history = DB::table('exploration_events')->get()->toJson();
        $this->rest($mediate);
        self::assertNotNull($mediate->cycle->fresh()->rested_at);
        self::assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_pending_evidence_cannot_qualify_an_exploration_completion(): void
    {
        [, , , $head, $terminal] = $this->settledJourney();
        self::assertNotNull(app(ExplorationEventService::class)->inspect($terminal)['witness']);
        app(EvidenceExecutionService::class)->start($terminal, $head, 'citation', $this->origin->id);
        self::assertNull(app(ExplorationEventService::class)->inspect($terminal)['witness']);
        self::assertDatabaseCount('exploration_events', 0);
        $plan = app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal);
        self::assertFalse($plan['rest_available']);
    }

    public function test_personal_variant_follows_complete_frontier_and_does_not_reset_history(): void
    {
        [$signal, , , $head, $terminal] = $this->settledJourney();
        $luna = $this->phase($this->origin, PhaseKind::Distinguish);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse($generation->available($luna, 'proposition.variant', prepared: true));
        $this->assertFalse(app(AcquisitionAttemptService::class)->allows($luna, 'instrument.execute'));
        $return = $this->returnAfterRest($terminal);
        $this->assertTrue($generation->available($return, 'proposition.variant', prepared: true));
        $this->instrumentGateFixture($return);
        $history = DB::table('exploration_events')->get()->toJson();
        $fresh = app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
            ['action' => 'proposition.variant', 'statement' => 'A personally earned alternative']);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($signal, $return), 'The new Origin observation reopens judgment on the old route');
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($fresh, $return));
        $this->assertFalse($generation->available($return, 'proposition.variant'), 'The new shared opportunity is evaluated before another Variant');
        $this->assertFalse($generation->available($luna, 'proposition.variant'));
        $this->assertDatabaseCount('exploration_events', 1);
    }

    public function test_new_sibling_reopens_movement_without_resetting_the_ancestors_judgment(): void
    {
        config(['schemas.options.limit' => 1]);
        [$signal, $persist, $constrain, $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertTrue($exploration->explored($signal, $return));
        $sibling = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        // This branch is active but cannot Reconcile with the first head.
        app(PropositionMeasurementService::class)->project($sibling, .8, .8, .8,
            correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $this->assertTrue($exploration->explored($signal, $return));
        $this->assertContains($signal->id, app(PropositionOptionService::class)->traversalOptionsFor($return)->pluck('id'), 'Navigation reaches the remaining unexplored head');
        $this->assertFalse(app(InputPlanService::class)->forPhase($return->cycle->participant, $return)['rest_available']);
        $this->assertCount(2, $exploration->heads($signal->id, $return, new ParticipationContext($return)), 'Evaluate the complete frontier before display limits');
        $this->assertTrue($exploration->explored($head, $return));
        $this->assertFalse(app(PhaseGenerationService::class)->available($return, 'proposition.variant'));
        $this->assertNotContains($signal->id, app(PropositionOptionService::class)->attestationOptionsFor($return)->pluck('id'));
        $phase = $return;
        foreach ([$signal, $persist, $constrain] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }
        $options = app(PropositionOptionService::class);
        $targets = $options->attestationOptionsFor($phase)->pluck('id');
        $this->assertContains($sibling->id, $targets);
        $this->assertNotContains($head->id, $targets, 'A new sibling does not reopen a judgment on the earlier head.');
        $this->personalVote($phase, $sibling, 'support');
        $terminal = app(PhaseProgressionService::class)->advance($phase, $sibling);
        $this->measure($sibling, 0);
        app(PropositionMeasurementService::class)->project($sibling, 0, 0, 0,
            correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $this->publishPotentialFixture($this->substrate);
        $return = $this->returnAfterRest($terminal);
        $this->assertDatabaseCount('exploration_events', 2);
        // Cycle closure refreshes the controlled measurements. Keep this test's
        // independent branch incoherent; lawful new Reconcile is covered separately.
        app(PropositionMeasurementService::class)->project($sibling, 0, 0, 0,
            correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $this->assertTrue($exploration->explored($signal, $return));
        $this->assertTrue(app(PhaseGenerationService::class)->available($return, 'proposition.variant', prepared: true));
    }

    public function test_reversal_and_unrelated_updates_do_not_reopen_and_cold_hydrated_plans_agree(): void
    {
        [$signal, , , $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $history = DB::table('exploration_events')->get()->toJson();
        $this->personalVote($return, $signal, 'refute');
        $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $exploration = app(ParticipantExplorationService::class);
        $context = new ParticipationContext($return);
        $this->assertTrue($exploration->explored($signal, $return));
        $this->assertSame($exploration->exploredIds($return, collect([$signal->id, $head->id]))->all(),
            $exploration->exploredIds($return, collect([$signal->id, $head->id]), $context)->all());
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $this->assertFalse(Schema::hasTable('continuation_failures'));
        $this->assertFalse(Schema::hasTable('horizon_exploration_states'));
        $this->assertFalse(Schema::hasTable('horizon_failure_witnesses'));
    }

    public function test_stale_uncompleted_and_rolled_back_rest_cannot_publish_events(): void
    {
        [, , , $head, $terminal] = $this->settledJourney();
        $events = app(ExplorationEventService::class);
        $proof = $events->inspect($terminal);
        $this->assertNotNull($proof['witness']);
        $events->publish($terminal, $proof);
        $this->assertDatabaseCount('exploration_events', 0);
        DB::beginTransaction();
        $this->rest($terminal);
        $this->assertDatabaseCount('exploration_events', 1);
        DB::rollBack();
        $this->assertDatabaseCount('exploration_events', 0);
        $terminal = $terminal->fresh();
        $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $terminal->forceFill(['completed_at' => now()])->save();
        $terminal->cycle->forceFill(['rested_at' => now(), 'rest_reason' => $proof['rest_reason']])->save();
        $events->publish($terminal, $proof);
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_unrecorded_arrival_or_untested_head_is_not_an_exploration_encounter(): void
    {
        [, , $constrain, $head, $terminal] = $this->settledJourney();
        $unproven = $this->phase($head, PhaseKind::Mediate);
        $this->rest($unproven);
        $this->assertDatabaseCount('exploration_events', 0);
        $unjudged = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        $untested = app(PhaseProgressionService::class)->advance($unjudged->phase, $unjudged);
        $this->measure($unjudged, 0);
        $this->rest($untested);
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_other_participants_judgments_reopen_a_route_when_they_enable_bias_generation(): void
    {
        [$signal, $persist, $constrain, $activity, $mediate] = $this->settledJourney();
        $stance = $this->node($mediate, $activity);
        $this->personalVote($mediate, $stance, 'refute');
        $bias = app(PhaseProgressionService::class)->advance($mediate, $stance);
        $this->coherentChiralityFixture($stance);
        $this->publishPotentialFixture($this->substrate);
        $this->assertFalse(app(PhaseGenerationService::class)->available($bias, 'proposition.reclassify'));
        $return = $this->returnAfterRest($bias);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertTrue($exploration->explored($stance, $return));
        $history = DB::table('exploration_events')->get()->toJson();
        $phase = $return;
        foreach ([$signal, $persist, $constrain, $activity] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }
        foreach (range(1, 2) as $judgment) {
            $other = $this->phase($activity, PhaseKind::Mediate);
            $changed = app(ContributionDispatcher::class)->dispatch($other->cycle->participant, $other,
                ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'support']);
        }
        $current = app(PhaseProgressionService::class)->advance($phase, $changed);
        $this->assertTrue(app(PhaseGenerationService::class)->available($current, 'proposition.reclassify'));
        foreach ([null, new ParticipationContext($current)] as $context) {
            $this->assertTrue($exploration->explored($stance, $current, $context));
            $this->assertTrue($exploration->explored($signal, $current, $context));
        }
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_new_reconciliation_opportunity_uses_the_personal_arrival_during_recovery(): void
    {
        [, , $constrain, $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $this->assertTrue(app(ParticipantExplorationService::class)->explored($head, $return));
        $counterpart = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        $this->measure($head, .8);
        $this->measure($counterpart, .8);
        app(PropositionMeasurementService::class)->project($counterpart, .8, .8, .8,
            correspondenceComponents: new CorrespondenceValues(.8, .8));
        $this->assertContains($counterpart->id, app(ReconciliationOptionService::class)->forPhase($terminal->fresh())->pluck('id'), json_encode([
            'head' => app(CorrespondenceGateService::class)->evaluate($head->fresh()),
            'counterpart' => app(CorrespondenceGateService::class)->evaluate($counterpart->fresh()),
            'anchor' => app(MediationAnchorService::class)->anchorFor($terminal->fresh())?->id,
            'reads' => app(MediationReadService::class)->readsFor($terminal->fresh())->pluck('id'),
            'gates' => app(MediationReadService::class)->gatesFor($terminal->fresh())->pluck('id'),
        ]));
        $this->assertTrue(app(ParticipantExplorationService::class)->explored($head, $return));
        $this->assertTrue(app(PropositionOptionService::class)->traversalOptionsFor($return)->isNotEmpty());
        $service = app(HomologousMediationService::class);
        $cold = $service->structuralPoolFor($terminal->fresh());
        $batch = $service->structuralPoolsFor(collect([$terminal->fresh()]), new ParticipationContext($terminal->fresh()));
        foreach (['reads', 'gates', 'population'] as $key) {
            $this->assertSame($cold[$key]->pluck('id')->all(), $batch['homologous-mediate-structure:'.$terminal->id][$key]->pluck('id')->all());
        }
        $this->assertDatabaseCount('exploration_events', 1);
    }

    public function test_historical_head_is_rechecked_when_reconsideration_completes_commit_grounding(): void
    {
        // Isolated history applicability, not an event-publication or natural Commit proof.
        [$signal, , , $activity, $mediate] = $this->settledJourney();
        $stance = $this->node($mediate, $activity);
        $this->personalVote($mediate, $stance);
        $bias = app(PhaseProgressionService::class)->advance($mediate, $stance);
        $head = $this->node($bias, $stance);
        $this->measure($head, .8);
        $instruments = app(EvidenceExecutionService::class);
        $instruments->complete($instruments->start($bias, $head, 'citation', $this->origin->id), $head);
        ExplorationHistory::record($head, $bias);
        $history = DB::table('exploration_events')->get()->toJson();
        $exploration = app(ParticipantExplorationService::class);
        self::assertFalse($exploration->explored($head, $bias), 'Historical encounter cannot waive a lawful first personal judgment');
        $changed = app(ContributionDispatcher::class)->dispatch($bias->cycle->participant, $bias,
            ['action' => 'attestation.create', 'proposition_id' => $head->id, 'direction' => 'support']);
        $order = app(PhaseProgressionService::class)->advance($bias, $changed);
        self::assertContains($head->id, app(ClosureOptionService::class)->committableFor($order)->pluck('id'));
        self::assertTrue($exploration->explored($head, $order));
        self::assertTrue($exploration->explored($signal, $order));
        self::assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_a_first_mediate_judgment_can_complete_one_branch_after_all_ordinary_receipts_are_consumed(): void
    {
        [$distinction, $persist, $constrain, $signal, $mediate] = $this->settledJourney();
        $sibling = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        foreach ([$signal, $sibling] as $operand) {
            app(PropositionMeasurementService::class)->project($operand, .8, .8, .8, correspondenceComponents: new CorrespondenceValues(.8, .8));
        }
        $other = app(PhaseProgressionService::class)->advance($this->phase($constrain, PhaseKind::Disperse), $signal);
        $stance = app(ContributionDispatcher::class)->dispatch($other->cycle->participant, $other,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $sibling->id, 'statement' => 'The observations share a common cause.']);
        $unjudged = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        $this->measure($unjudged, 0);
        $receipts = app(HorizonReceiptService::class);
        DB::transaction(function () use ($receipts, $mediate, $constrain, $persist) {
            foreach ($receipts->ordinaryAvailable($mediate) as $receipt) {
                $receipts->consume($constrain->phase, $persist, $constrain, $receipt->id);
            }
        });
        self::assertEmpty($receipts->ordinaryAvailable($mediate));
        $actor = $mediate->cycle->participant;
        $schema = app(InputPlanService::class)->forPhase($actor, $mediate)['schema']['$defs']['actions'];
        self::assertSame([$stance->id], $schema['attestation.create']['properties']['proposition_id']['enum']);
        self::assertArrayNotHasKey('participant.rest', $schema);
        $this->publishPotentialFixture($this->substrate);
        app(ParticipationService::class)->contribute($actor, $mediate->id,
            ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'refute']);
        $bias = app(PhaseService::class)->currentFor($actor->fresh());
        self::assertSame($stance->id, $bias->gate_proposition_id);
        self::assertSame(['participant.rest'], array_keys(app(InputPlanService::class)->forPhase($actor, $bias)['schema']['$defs']['actions']));
        $return = $this->returnAfterRest($bias);
        $event = DB::table('exploration_events')->sole();
        self::assertSame($stance->id, $event->proposition_id);
        $witness = json_decode($event->witness, true);
        self::assertSame($stance->effectiveAttestations()->sole()->id, $witness['attestation_id']);
        self::assertArrayNotHasKey('receipt_id', $witness);
        $exploration = app(ParticipantExplorationService::class);
        self::assertTrue($exploration->explored($signal, $return));
        self::assertTrue($exploration->explored($constrain, $return));
        self::assertFalse($exploration->explored($unjudged, $return));
        $disperse = $return;
        foreach ([$distinction, $persist, $constrain] as $target) {
            app(ParticipationService::class)->contribute($actor, $disperse->id, ['action' => 'proposition.traverse', 'proposition_id' => $target->id]);
            $disperse = app(PhaseService::class)->currentFor($actor->fresh());
        }
        // The new sibling still offers a first vote. Its coherence cannot reopen
        // a completed route through a Signal that is already bound.
        $measures = app(PropositionMeasurementService::class);
        $measures->project($unjudged, .8, .8, .8, correspondenceComponents: new CorrespondenceValues(.8, .8));
        $options = app(PropositionOptionService::class);
        self::assertEmpty(app(ReconciliationOptionService::class)->forPhase($other->fresh()));
        self::assertFalse($options->traversalOptionsFor($disperse)->contains('id', $signal->id));
        self::assertTrue($options->attestationOptionsFor($disperse)->contains('id', $unjudged->id));
        $measures->project($unjudged, 0, .8, .8, correspondenceComponents: new CorrespondenceValues(-.8, .8));
        self::assertEmpty(app(ReconciliationOptionService::class)->forPhase($other->fresh()));
        self::assertFalse($options->traversalOptionsFor($disperse)->contains('id', $signal->id));
        self::assertTrue($options->attestationOptionsFor($disperse)->contains('id', $unjudged->id));
        self::assertEmpty($options->instrumentOptionsFor($disperse));
    }

    public function test_a_new_ancestral_receipt_reopens_judgment_without_a_generating_opportunity(): void
    {
        [$signal, , , $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $instruments = app(EvidenceExecutionService::class);
        $observation = $this->phase($signal, PhaseKind::Persist);
        $instruments->complete($instruments->start($observation, $signal, 'citation', $this->origin->id), $signal);
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($head, $return));
        $this->assertDatabaseCount('exploration_events', 1);
    }

    public function test_new_evidence_can_open_reclassification_and_reconsideration_independently(): void
    {
        [$signal, $persist, $constrain, $activity, $mediate] = $this->settledJourney();
        $stance = $this->node($mediate, $activity);
        $this->personalVote($mediate, $stance, 'refute');
        $bias = app(PhaseProgressionService::class)->advance($mediate, $stance);
        $this->coherentChiralityFixture($stance);
        $this->publishPotentialFixture($this->substrate);
        $return = $this->returnAfterRest($bias);
        $history = DB::table('exploration_events')->get()->toJson();
        $receipts = app(HorizonReceiptService::class);
        $available = $receipts->ordinaryAvailable($return);
        $phase = $return;
        foreach ([$signal, $persist, $constrain, $activity] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }
        foreach (range(1, 2) as $judgment) {
            $other = $this->phase($activity, PhaseKind::Mediate);
            $changed = app(ContributionDispatcher::class)->dispatch($other->cycle->participant, $other,
                ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'support']);
        }
        $bias = app(PhaseProgressionService::class)->advance($phase, $changed);
        foreach ($available as $receipt) {
            DB::transaction(fn () => $receipts->consume($constrain->phase, $persist, $constrain, $receipt->id));
        }
        $this->assertFalse(app(PhaseGenerationService::class)->available($bias, 'proposition.reclassify'));
        $this->assertTrue(app(ParticipantExplorationService::class)->explored($stance, $bias));
        $this->publishPotentialFixture($this->substrate);
        $this->assertNotNull(app(ExplorationEventService::class)->inspect($bias)['witness'], 'A hypothetical Reclassification cannot block completion');
        $observation = $this->phase($signal, PhaseKind::Persist);
        $instruments = app(EvidenceExecutionService::class);
        $instruments->complete($instruments->start($observation, $signal, 'citation', $this->origin->id), $signal);
        $this->assertTrue(app(PhaseGenerationService::class)->available($bias, 'proposition.reclassify'));
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($stance, $bias));
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_hard_revision_preserves_the_encounter_until_forward_progress_becomes_available(): void
    {
        [$signal, , $constrain, $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $revision = $this->node($this->phase($constrain, PhaseKind::Disperse), $head);
        Supersession::where('source_proposition_id', $head->id)->where('target_proposition_id', $revision->id)->update(['type' => 'revise']);
        $context = new ParticipationContext($return);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertSame([$revision->id], $exploration->heads($signal->id, $return, $context)->keys()->all());
        self::assertTrue($exploration->explored($signal, $return, $context));
        self::assertFalse($exploration->explored($revision, $return));
        $forward = $this->node($this->phase($revision, PhaseKind::Mediate), $revision);
        self::assertTrue($exploration->explored($signal, $return));
        self::assertFalse($exploration->explored($forward, $return));
        $this->assertDatabaseHas('exploration_events', ['proposition_id' => $head->id]);
    }

    public function test_a_reconcile_counterpart_edge_does_not_authorize_the_other_operands_route(): void
    {
        [$signal, , $constrain, $head, $terminal] = $this->settledJourney();
        $foreignEntry = $this->commitFrom($signal);
        $other = $this->node($this->phase($foreignEntry, PhaseKind::Disperse), $foreignEntry);
        $otherMediate = $this->phase($other, PhaseKind::Mediate);
        $result = $this->node($otherMediate, $other);
        Supersession::where('target_proposition_id', $result->id)->update(['type' => 'reconcile']);
        (new Supersession)->forceFill(['phase_id' => $otherMediate->id, 'source_proposition_id' => $head->id,
            'target_proposition_id' => $result->id, 'type' => 'reconcile'])->save();
        $context = new ParticipationContext($terminal);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertFalse($exploration->heads($head->id, $terminal, $context)->has($result->id));
        $foreignContext = new ParticipationContext($otherMediate);
        $this->assertSame([$result->id], $exploration->heads($other->id, $otherMediate, $foreignContext)->keys()->all());
        $this->assertFalse($exploration->heads($head->id, $terminal, $context)->has($head->id), 'Hard-superseded inputs are not heads');
        $entry = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->instrumentGateFixture($entry);
        $this->assertEmpty($exploration->heads($signal->id, $entry, new ParticipationContext($entry)));
        $this->assertFalse($exploration->explored($signal, $entry), 'Retired history does not invent a personal encounter');
        $this->assertTrue(app(PhaseGenerationService::class)->available($entry, 'proposition.variant'), 'No active authorized continuation remains to explore');
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_local_reconciliation_preserves_the_other_operands_existing_attestation_route(): void
    {
        [, , $constrain, $head, $terminal] = $this->settledJourney();
        $other = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        $producer = $this->phase($other, PhaseKind::Mediate);
        $result = $this->node($producer, $other);
        Supersession::where('target_proposition_id', $result->id)->update(['type' => 'reconcile']);
        (new Supersession)->forceFill(['phase_id' => $producer->id, 'source_proposition_id' => $head->id,
            'target_proposition_id' => $result->id, 'type' => 'reconcile'])->save();
        $context = new ParticipationContext($terminal);
        $this->assertContains($result->id, app(PropositionOptionService::class)->attestationOptionsFor($terminal, $context)->pluck('id'));
        $this->assertSame([$result->id], app(ParticipantExplorationService::class)->heads($head->id, $terminal, $context)->keys()->all());
    }

    public function test_event_publication_checks_frozen_arrival_identity_and_retries_are_idempotent(): void
    {
        [, , , $head, $terminal] = $this->settledJourney();
        $events = app(ExplorationEventService::class);
        $proof = $events->inspect($terminal);
        $terminal->forceFill(['completed_at' => now()])->save();
        $terminal->cycle->forceFill(['rested_at' => now(), 'rest_reason' => $proof['rest_reason']])->save();
        $invalid = $proof;
        $invalid['witness']['arrival_source_id'] = $this->origin->id;
        $events->publish($terminal, $invalid);
        $this->assertDatabaseCount('exploration_events', 0);
        $events->publish($terminal, $proof);
        $this->assertDatabaseCount('exploration_events', 1);
        $history = DB::table('exploration_events')->get()->toJson();
        $events->publish($terminal, $proof);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $this->assertSame($head->id, json_decode(DB::table('exploration_events')->value('witness'), true)['head_id']);
    }

    public function test_hydrated_evaluation_memoizes_the_full_frontier_without_per_ancestor_queries(): void
    {
        [$signal, $persist, $constrain, $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $context = new ParticipationContext($return);
        $ids = collect([$signal->id, $persist->id, $constrain->id, $head->id]);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertCount(4, $exploration->exploredIds($return, $ids, $context));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertCount(4, $exploration->exploredIds($return, $ids, $context));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_personal_events_are_append_only(): void
    {
        [, , , , $terminal] = $this->settledJourney();
        $this->returnAfterRest($terminal);
        $this->expectException(QueryException::class);
        DB::table('exploration_events')->where('phase_id', $terminal->id)->update(['observed_field_version' => 0]);
    }

    #[Group('query')]
    public function test_generative_traverse_cost_is_bounded_across_explored_sibling_routes(): void
    {
        [$signal, $persist, $constrain, $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $gates = collect([$constrain->id]);
        $heads = collect([$head]);
        $counts = [];
        $visit = null;
        foreach ([2, 8] as $size) {
            while ($gates->count() < $size) {
                // Controlled historical structure. Each personal encounter is
                // earned through Phase arrivals, judgments and a real Mediate Rest.
                $next = $this->node($this->phase($persist, PhaseKind::Constrain), $persist);
                $leaf = $this->node($this->phase($next, PhaseKind::Disperse), $next);
                $phase = $visit ?? $return;
                $route = $visit === null ? [$signal, $persist, $next, $leaf] : [$next, $leaf];
                $visit = null;
                foreach ($route as $node) {
                    if ($node->is($next) || $node->is($leaf)) {
                        $this->personalVote($phase, $node);
                    }
                    $phase = app(PhaseProgressionService::class)->advance($phase, $node);
                }
                $heads->push($leaf);
                $gates->push($next->id);
                $this->measure($leaf, 0);
                app(PropositionMeasurementService::class)->project($leaf, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(.8, -.8));
                $this->publishPotentialFixture($this->substrate);
                $return = $this->returnAfterRest($phase);
            }
            foreach ($heads as $leaf) {
                app(PropositionMeasurementService::class)->project($leaf, 0, 0, 0, correspondenceComponents: new CorrespondenceValues(.8, -.8));
            }
            $this->assertDatabaseCount('exploration_events', $size);
            $phase = $return;
            foreach ([$signal, $persist] as $node) {
                $phase = app(PhaseProgressionService::class)->advance($phase, $node);
            }
            $context = new ParticipationContext($phase);
            $options = app(PropositionOptionService::class);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $offered = $options->traversalOptionsFor($phase, $context)->pluck('id');
            $cold = DB::getQueryLog();
            DB::flushQueryLog();
            self::assertEqualsCanonicalizing($gates->all(), $offered->all());
            self::assertSame($offered->all(), $options->traversalOptionsFor($phase, $context)->pluck('id')->all());
            self::assertCount(0, DB::getQueryLog());
            DB::disableQueryLog();
            foreach ($cold as $query) {
                $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update/i', $query['query']);
            }
            self::assertCount($size, app(ParticipantExplorationService::class)->exploredIds($phase, $gates, $context));
            $counts[] = count($cold);
            $visit = $phase; // Continue from this Constrain arrival on the next branch.
        }
        fwrite(STDERR, "\nGENERATIVE_TRAVERSE queries (2 -> 8 routes): ".implode(' -> ', $counts)."; warm 0 -> 0\n");
        self::assertLessThanOrEqual($counts[0], $counts[1]);
    }

    #[Group('query')]
    public function test_cold_frontier_queries_are_batched_as_personal_heads_grow(): void
    {
        [, , $constrain, $head, $terminal] = $this->settledJourney();
        $return = $this->returnAfterRest($terminal);
        $ids = collect([$head->id]);
        $counts = [];
        $runtime = [];
        foreach ([2, 8] as $size) {
            while ($ids->count() < $size) {
                $node = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
                $this->personalVote($head->phase, $node);
                $this->measure($node, 0);
                ExplorationHistory::record($node, $return);
                $ids->push($node->id);
            }
            $context = new ParticipationContext($return);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $started = hrtime(true);
            $explored = app(ParticipantExplorationService::class)->exploredIds($return, $ids, $context);
            $runtime[] = round((hrtime(true) - $started) / 1e6, 2);
            self::assertCount($size, $explored);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $counts[] = count($queries);
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update/i', $query['query']);
            }
        }
        fwrite(STDERR, "\nPERSONAL_FRONTIER queries (2 -> 8 heads): ".$counts[0].' -> '.$counts[1].'; runtime ms: '.json_encode($runtime)."\n");
        $this->assertLessThanOrEqual($counts[0], $counts[1], json_encode($counts));
    }
}
