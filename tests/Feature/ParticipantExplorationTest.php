<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
use App\Enums\PhaseKind;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\CorrespondenceGateService;
use App\Services\EvidenceExecutionService;
use App\Services\ExplorationEventService;
use App\Services\HomologousMediationService;
use App\Services\HorizonReceiptService;
use App\Services\MediationAnchorService;
use App\Services\MediationReadService;
use App\Services\ParticipantExplorationService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseGenerationService;
use App\Services\PhaseProgressionService;
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

    public function test_completed_rest_records_one_personal_head_and_preserves_other_participants_actions(): void
    {
        [$signal, , , $head, $terminal] = $this->personalJourney();
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
        $this->assertContains($signal->id, $options->attestationOptionsFor($return)->pluck('id'), 'Repeat judgment remains lawful');
        $this->assertNotContains($signal->id, $options->traversalOptionsFor($return)->pluck('id'));
        $this->assertSame($nova->id, $return->cycle->participant_id);
        $this->assertSame(0, Tragedy::count());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_personal_variant_follows_complete_frontier_and_does_not_reset_history(): void
    {
        [$signal, , , $head, $terminal] = $this->personalJourney();
        $luna = $this->phase($this->origin, PhaseKind::Distinguish);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse($generation->available($luna, 'proposition.variant'));
        $return = $this->returnAfterRest($terminal);
        $this->assertTrue($generation->available($return, 'proposition.variant'));
        $history = DB::table('exploration_events')->get()->toJson();
        $fresh = app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
            ['action' => 'proposition.variant', 'statement' => 'A personally earned alternative']);
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
        $this->assertTrue(app(ParticipantExplorationService::class)->explored($signal, $return), 'An independent sibling does not erase the old route');
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($fresh, $return));
        $this->assertFalse($generation->available($return, 'proposition.variant'), 'The new shared opportunity is evaluated before another Variant');
        $this->assertFalse($generation->available($luna, 'proposition.variant'));
        $this->assertDatabaseCount('exploration_events', 1);
    }

    public function test_branching_ancestor_requires_all_active_heads_and_a_new_head_reopens_it(): void
    {
        config(['schemas.options.limit' => 1]);
        [$signal, $persist, $constrain, $head, $terminal] = $this->personalJourney();
        $return = $this->returnAfterRest($terminal);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertTrue($exploration->explored($signal, $return));
        $sibling = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        // This branch is active but cannot Reconcile with the first head.
        app(PropositionMeasurementService::class)->project($sibling, .8, .8, .8,
            correspondenceComponents: new CorrespondenceValues(.8, -.8));
        $this->assertFalse($exploration->explored($signal, $return));
        $this->assertCount(2, $exploration->heads($signal->id, $return, new ParticipationContext($return)), 'Evaluate the complete frontier before display limits');
        $this->assertTrue($exploration->explored($head, $return));
        $this->assertFalse(app(PhaseGenerationService::class)->available($return, 'proposition.variant'));
        $phase = $return;
        foreach ([$signal, $persist, $constrain] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }
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
        $this->assertTrue(app(PhaseGenerationService::class)->available($return, 'proposition.variant'));
    }

    public function test_reversal_and_unrelated_updates_do_not_reopen_and_cold_hydrated_plans_agree(): void
    {
        [$signal, , , $head, $terminal] = $this->personalJourney();
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
        [, , , $head, $terminal] = $this->personalJourney();
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
        [, , $constrain, $head, $terminal] = $this->personalJourney();
        $unproven = $this->phase($head, PhaseKind::Mediate);
        $this->rest($unproven);
        $this->assertDatabaseCount('exploration_events', 0);
        $unjudged = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
        $untested = app(PhaseProgressionService::class)->advance($unjudged->phase, $unjudged);
        $this->measure($unjudged, 0);
        $this->rest($untested);
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_a_lawful_attestation_reversal_reopens_only_when_it_enables_bias_progression(): void
    {
        [$signal, $persist, $constrain, $activity, $mediate] = $this->personalJourney();
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
        // Ordinary direction changes stay authorized even on personally explored Writes.
        $changed = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'support']);
        $current = app(PhaseProgressionService::class)->advance($phase, $changed);
        $this->assertTrue(app(PhaseGenerationService::class)->available($current, 'proposition.reclassify'));
        foreach ([null, new ParticipationContext($current)] as $context) {
            $this->assertFalse($exploration->explored($stance, $current, $context));
            $this->assertFalse($exploration->explored($signal, $current, $context));
        }
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_new_reconciliation_opportunity_uses_the_personal_arrival_during_recovery(): void
    {
        [, , $constrain, $head, $terminal] = $this->personalJourney();
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
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($head, $return), 'The returning Cycle has no Disperse Phase; use the recorded personal arrival');
        $service = app(HomologousMediationService::class);
        $cold = $service->structuralPoolFor($terminal->fresh());
        $batch = $service->structuralPoolsFor(collect([$terminal->fresh()]), new ParticipationContext($terminal->fresh()));
        foreach (['reads', 'gates', 'population'] as $key) {
            $this->assertSame($cold[$key]->pluck('id')->all(), $batch['homologous-mediate-structure:'.$terminal->id][$key]->pluck('id')->all());
        }
        $this->assertDatabaseCount('exploration_events', 1);
    }

    public function test_new_receipt_alone_does_not_reopen_a_head_without_advancing_opportunities(): void
    {
        [$signal, , , $head, $terminal] = $this->personalJourney();
        $return = $this->returnAfterRest($terminal);
        $instruments = app(EvidenceExecutionService::class);
        $observation = $this->phase($signal, PhaseKind::Persist);
        $instruments->complete($instruments->start($observation, $signal, 'citation', $this->origin->id), $signal);
        $this->assertTrue(app(ParticipantExplorationService::class)->explored($head, $return));
        $this->assertDatabaseCount('exploration_events', 1);
    }

    public function test_a_new_receipt_reopens_only_after_it_completes_a_lawful_advancing_move(): void
    {
        [$signal, $persist, $constrain, $activity, $mediate] = $this->personalJourney();
        $stance = $this->node($mediate, $activity);
        $this->personalVote($mediate, $stance, 'refute');
        $bias = app(PhaseProgressionService::class)->advance($mediate, $stance);
        $this->coherentChiralityFixture($stance);
        $this->publishPotentialFixture($this->substrate);
        $return = $this->returnAfterRest($bias);
        $history = DB::table('exploration_events')->get()->toJson();
        $receipts = app(HorizonReceiptService::class);
        $receipt = $receipts->ordinaryAvailable($return)->sole();
        $phase = $return;
        foreach ([$signal, $persist, $constrain, $activity] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }
        $changed = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'support']);
        $bias = app(PhaseProgressionService::class)->advance($phase, $changed);
        DB::transaction(fn () => $receipts->consume($constrain->phase, $persist, $constrain, $receipt->id));
        $this->assertFalse(app(PhaseGenerationService::class)->available($bias, 'proposition.reclassify'));
        $this->assertTrue(app(ParticipantExplorationService::class)->explored($stance, $bias));
        $this->publishPotentialFixture($this->substrate);
        $this->assertNull(app(ExplorationEventService::class)->inspect($bias)['witness'], 'Receipt shortage cannot mint another completed encounter');
        $observation = $this->phase($signal, PhaseKind::Persist);
        $instruments = app(EvidenceExecutionService::class);
        $instruments->complete($instruments->start($observation, $signal, 'citation', $this->origin->id), $signal);
        $this->assertTrue(app(PhaseGenerationService::class)->available($bias, 'proposition.reclassify'));
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($stance, $bias));
        $this->assertSame($history, DB::table('exploration_events')->get()->toJson());
    }

    public function test_hard_revision_replaces_the_head_and_reopens_only_its_forward_lineage(): void
    {
        [$signal, , $constrain, $head, $terminal] = $this->personalJourney();
        $return = $this->returnAfterRest($terminal);
        $revision = $this->node($this->phase($constrain, PhaseKind::Disperse), $head);
        Supersession::where('source_proposition_id', $head->id)->where('target_proposition_id', $revision->id)->update(['type' => 'revise']);
        $context = new ParticipationContext($return);
        $exploration = app(ParticipantExplorationService::class);
        $this->assertSame([$revision->id], $exploration->heads($signal->id, $return, $context)->keys()->all());
        $this->assertFalse($exploration->explored($signal, $return, $context));
        $this->assertDatabaseHas('exploration_events', ['proposition_id' => $head->id]);
    }

    public function test_a_reconcile_counterpart_edge_does_not_authorize_the_other_operands_route(): void
    {
        [$signal, , $constrain, $head, $terminal] = $this->personalJourney();
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
        $this->assertEmpty($exploration->heads($signal->id, $entry, new ParticipationContext($entry)));
        $this->assertFalse($exploration->explored($signal, $entry), 'Retired history does not invent a personal encounter');
        $this->assertTrue(app(PhaseGenerationService::class)->available($entry, 'proposition.variant'), 'No active authorized continuation remains to explore');
        $this->assertDatabaseCount('exploration_events', 0);
    }

    public function test_local_reconciliation_preserves_the_other_operands_existing_attestation_route(): void
    {
        [, , $constrain, $head, $terminal] = $this->personalJourney();
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
        [, , , $head, $terminal] = $this->personalJourney();
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
        [$signal, $persist, $constrain, $head, $terminal] = $this->personalJourney();
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
        [, , , , $terminal] = $this->personalJourney();
        $this->returnAfterRest($terminal);
        $this->expectException(QueryException::class);
        DB::table('exploration_events')->where('phase_id', $terminal->id)->update(['observed_field_version' => 0]);
    }

    #[Group('query')]
    public function test_cold_frontier_queries_are_batched_as_personal_heads_grow(): void
    {
        [, , $constrain, $head, $terminal] = $this->personalJourney();
        $return = $this->returnAfterRest($terminal);
        $ids = collect([$head->id]);
        $counts = [];
        foreach ([2, 8] as $size) {
            while ($ids->count() < $size) {
                $node = $this->node($this->phase($constrain, PhaseKind::Disperse), $constrain);
                ExplorationHistory::record($node, $return);
                $ids->push($node->id);
            }
            $context = new ParticipationContext($return);
            DB::enableQueryLog();
            DB::flushQueryLog();
            app(ParticipantExplorationService::class)->exploredIds($return, $ids, $context);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $counts[] = count($queries);
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update/i', $query['query']);
            }
        }
        fwrite(STDERR, "\nPERSONAL_FRONTIER queries (2 -> 8 heads): ".$counts[0].' -> '.$counts[1]."\n");
        $this->assertLessThanOrEqual($counts[0], $counts[1], json_encode($counts));
    }
}
