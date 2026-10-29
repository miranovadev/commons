<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Supersession;
use App\Services\ExplorationEventService;
use App\Services\ParticipantExplorationService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseProgressionService;
use App\Services\PhaseService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExplorationHistory;
use Tests\Fixtures\ParticipantExplorationFixture;
use Tests\TestCase;

class ParticipatoryCompletionTest extends TestCase
{
    use ParticipantExplorationFixture, RefreshDatabase;

    public static function judgments(): array
    {
        return [['support'], ['refute']];
    }

    #[DataProvider('judgments')]
    public function test_witness_uses_exact_personal_head_judgment(string $direction): void
    {
        [, , , $head, $terminal] = $this->personalJourney(omitJudgment: 4);
        $other = $this->phase($head, PhaseKind::Mediate);
        $foreign = $this->personalVote($other, $head);
        $events = app(ExplorationEventService::class);
        self::assertNull($events->completionWitness($terminal, new ParticipationContext($terminal)));
        $personal = $this->personalVote($head->phase, $head, $direction);
        $proof = $events->completionWitness($terminal, new ParticipationContext($terminal));
        self::assertSame($personal->id, $proof['attestation_id']);
        self::assertNotSame($foreign->id, $proof['attestation_id']);
        self::assertSame($terminal->cycle->participant_id, $proof['participant_id']);
        self::assertSame(2, $proof['contract']);
    }

    public static function missingJudgments(): array
    {
        return [[1], [2], [3], [4]];
    }

    #[DataProvider('missingJudgments')]
    public function test_unjudged_active_route_cannot_publish_or_hide_behind_historical_event(int $missing): void
    {
        [$a, $b, $c, $head, $terminal] = $this->personalJourney(omitJudgment: $missing);
        $nodes = [$a, $b, $c, $head];
        ExplorationHistory::record($head, $terminal);
        $before = DB::table('exploration_events')->get()->toJson();
        $context = new ParticipationContext($terminal);
        self::assertFalse(app(ParticipantExplorationService::class)->explored($a, $terminal, $context));
        self::assertNull(app(ExplorationEventService::class)->completionWitness($terminal, $context));
        $this->personalVote($nodes[$missing - 1]->phase, $nodes[$missing - 1], 'refute');
        self::assertNotNull(app(ExplorationEventService::class)->completionWitness($terminal, new ParticipationContext($terminal)));
        self::assertSame($before, DB::table('exploration_events')->get()->toJson());
    }

    public static function acquisitionRoles(): array
    {
        return [[1], [2], [4]];
    }

    #[DataProvider('acquisitionRoles')]
    public function test_shared_observations_do_not_complete_personal_route_acquisition(int $role): void
    {
        [, , , $head, $terminal] = $this->personalJourney(omitAcquisition: $role);
        ExplorationHistory::record($head, $terminal);
        self::assertNull(app(ExplorationEventService::class)->completionWitness($terminal, new ParticipationContext($terminal)));
        self::assertFalse(app(ParticipantExplorationService::class)->explored($head, $terminal));
        $before = DB::table('exploration_events')->count();
        $this->rest($terminal);
        self::assertSame($before, DB::table('exploration_events')->count(), 'Actionless Rest is not completed exploration');
    }

    public function test_incomplete_rest_recovers_to_existing_first_judgment_without_reacquiring(): void
    {
        [$a, , , , $terminal] = $this->personalJourney(omitJudgment: 1);
        $this->rest($terminal);
        self::assertDatabaseCount('exploration_events', 0);
        $return = app(PhaseService::class)->currentFor($terminal->cycle->participant->fresh());
        self::assertSame($this->origin->id, $return->gate_proposition_id);
        $options = app(PropositionOptionService::class);
        self::assertContains($a->id, $options->attestationOptionsFor($return)->pluck('id'));
        self::assertEmpty($options->instrumentOptionsFor($return), 'Completed acquisition survives incomplete Rest');
        self::assertNotContains($a->id, $options->traversalOptionsFor($return)->pluck('id'), 'No invented first-judgment traversal');
    }

    public function test_sibling_missing_judgment_does_not_block_completed_route(): void
    {
        [, , $c, $head, $terminal] = $this->personalJourney();
        $sibling = $this->node($this->phase($c, PhaseKind::Disperse), $c);
        $this->measure($sibling, 0);
        self::assertTrue(app(ParticipantExplorationService::class)->participationComplete($head, $terminal, new ParticipationContext($terminal)));
        self::assertFalse(app(ParticipantExplorationService::class)->participationComplete($sibling, $terminal, new ParticipationContext($terminal)));
    }

    public function test_hard_superseded_route_node_does_not_impose_a_judgment_quota(): void
    {
        [, $b, $c, $head, $terminal] = $this->personalJourney(omitJudgment: 2);
        self::assertFalse(app(ParticipantExplorationService::class)->participationComplete($head, $terminal, new ParticipationContext($terminal)));
        // Isolated topology transition; historical judgments are not fabricated.
        DB::table('supersessions')->where('source_proposition_id', $b->id)->where('target_proposition_id', $c->id)->update(['type' => 'reclassify']);
        self::assertTrue(app(ParticipantExplorationService::class)->participationComplete($head, $terminal, new ParticipationContext($terminal)));
    }

    public function test_counterpart_ancestry_does_not_import_personal_obligations(): void
    {
        [, , , $head, $mediate] = $this->personalJourney();
        $otherGate = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $other = $this->node($this->phase($otherGate, PhaseKind::Disperse), $otherGate);
        $result = $this->node($mediate, $head);
        DB::table('supersessions')->where('target_proposition_id', $result->id)->update(['type' => 'reconcile']);
        (new Supersession)->forceFill(['phase_id' => $mediate->id, 'source_proposition_id' => $other->id,
            'target_proposition_id' => $result->id, 'type' => 'reconcile'])->save();
        $this->personalVote($mediate, $result);
        $bias = app(PhaseProgressionService::class)->advance($mediate, $result);
        self::assertTrue(app(ParticipantExplorationService::class)->participationComplete($result, $bias, new ParticipationContext($bias)),
            'Only the actual local arrival supplies the personal route');
        self::assertFalse(app(ParticipantExplorationService::class)->participationComplete($result, $bias, new ParticipationContext($bias), $otherGate->id),
            'An independently selected local route retains its own acquisition obligation');
    }

    public function test_publication_rejects_substituted_foreign_attestation_under_the_same_field_version(): void
    {
        [, , , $head, $terminal] = $this->personalJourney();
        $foreign = $this->personalVote($this->phase($head, PhaseKind::Mediate), $head);
        $this->publishPotentialFixture($this->substrate);
        $events = app(ExplorationEventService::class);
        $proof = $events->inspect($terminal);
        self::assertNotNull($proof['witness']);
        $terminal->forceFill(['completed_at' => now()])->save();
        $terminal->cycle->forceFill(['rested_at' => now(), 'rest_reason' => $proof['rest_reason']])->save();
        $forged = $proof;
        $forged['witness']['attestation_id'] = $foreign->id;
        $events->publish($terminal, $forged);
        self::assertDatabaseCount('exploration_events', 0);
        $events->publish($terminal, $proof);
        $events->publish($terminal, $proof);
        self::assertDatabaseCount('exploration_events', 1);
    }

    public function test_converging_authorised_branches_do_not_hide_the_unfinished_route(): void
    {
        [$a, $b, , $head, $mediate] = $this->personalJourney();
        $rightB = $this->node($this->phase($a, PhaseKind::Persist), $a);
        $rightC = $this->node($this->phase($rightB, PhaseKind::Constrain), $rightB);
        $rightHead = $this->node($this->phase($rightC, PhaseKind::Disperse), $rightC);
        $result = $this->node($mediate, $head);
        DB::table('supersessions')->where('target_proposition_id', $result->id)->update(['type' => 'reconcile']);
        (new Supersession)->forceFill(['phase_id' => $mediate->id, 'source_proposition_id' => $rightHead->id,
            'target_proposition_id' => $result->id, 'type' => 'reconcile'])->save();
        $this->personalVote($mediate, $result);
        $bias = app(PhaseProgressionService::class)->advance($mediate, $result);
        $service = app(ParticipantExplorationService::class);
        $context = new ParticipationContext($bias);
        self::assertTrue($service->participationComplete($result, $bias, $context, $b->id));
        self::assertFalse($service->participationComplete($result, $bias, $context, $a->id),
            'The shared ancestor includes both authorised routes to the same head');
    }
}
