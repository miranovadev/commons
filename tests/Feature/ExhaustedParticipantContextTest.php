<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\User;
use App\Services\ContextHorizonProjector;
use App\Services\InputPlanService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantExplorationService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PersonalAcquisitionService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ExplorationHistory;
use Tests\Fixtures\HorizonProofFixture;
use Tests\TestCase;

class ExhaustedParticipantContextTest extends TestCase
{
    use HorizonProofFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public static function writePositions(): array
    {
        $cases = [];
        foreach (ContextDomain::cases() as $domain) {
            foreach ([PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse, PhaseKind::Mediate, PhaseKind::Bias] as $kind) {
                $cases[$domain->value.'-'.$kind->name] = [$domain, $kind];
            }
        }

        return $cases;
    }

    #[DataProvider('writePositions')]
    public function test_historical_encounters_without_personal_judgment_preserve_structural_visibility_in_both_domains(ContextDomain $domain, PhaseKind $kind): void
    {
        if ($domain === ContextDomain::Settlement) {
            $snapshot = $this->origin->commitmentSnapshot;
            $snapshot->forceFill(['direction_after' => $domain])->save();
            app(ContextHorizonProjector::class)->apply($snapshot);
        }
        $gate = $this->origin;
        for ($number = 1; $number < $kind->value; $number++) {
            $gate = $this->node($this->phase($gate, PhaseKind::from($number)), $gate);
        }
        $right = $this->node($this->phase($gate, $kind), $gate);
        $wrongKind = PhaseKind::from($kind->value > 3 ? $kind->value - 3 : $kind->value + 3);
        $wrong = $this->node($this->phase($gate, $wrongKind), $gate);
        $this->assertSame($right->register, $wrong->register, 'Labels alone cannot supply a structural position');
        $phase = $this->phase($gate, $kind);
        ExplorationHistory::record($right, $phase);
        ExplorationHistory::record($wrong, $phase);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase, true);
        $this->assertContains($right->id, array_column($plan['write_options'], 'id'));
        $this->assertNotContains($wrong->id, array_column($plan['write_options'], 'id'));
        foreach (['traversal_options', 'attestation_options', 'instrument_options', 'revision_options'] as $key) {
            $this->assertNotContains($right->id, array_column($plan[$key], 'id'));
        }
        $this->assertSame([], $plan['exhausted_alternatives'], 'Explored is not Horizon Exhaustion');
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($right, $phase),
            'An historical encounter without personal judgment does not establish participatory completion');
        $compact = array_column(app(ParticipantContextSerializer::class)->plan($plan)['write_options'], 'id');
        $this->assertNotContains($right->id, $compact,
            'The operational serializer does not promote an unavailable, unevaluated Write through a historical event');
        $this->assertDatabaseCount('exploration_events', 2);
    }

    public function test_a_healthy_sibling_remains_actionable_alongside_visible_explored_evidence(): void
    {
        $creating = $this->phase($this->origin, PhaseKind::Distinguish);
        $explored = $this->node($creating, $this->origin);
        $healthy = $this->node($creating, $this->origin);
        $this->explored($explored);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $plan = app(InputPlanService::class)->forParticipant($phase->cycle->participant);
        $this->assertEqualsCanonicalizing([$explored->id, $healthy->id], array_column($plan['write_options'], 'id'));
        $this->assertSame([$phase->gate_proposition_id], array_column($plan['instrument_options'], 'id'), 'Existing structure does not waive personal discovery');
        $this->assertArrayNotHasKey('proposition.variant', $plan['schema']['$defs']['actions']);
    }

    public function test_context_is_local_and_not_cut_off_by_the_read_limit(): void
    {
        $creating = $this->phase($this->origin, PhaseKind::Distinguish);
        $roots = collect(range(1, 30))->map(fn () => $this->node($creating, $this->origin));
        $this->explored(...$roots);
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $phase->cycle->participant->forceFill(['max_read_count' => 1])->save();
        $childPhase = $this->phase($roots->first(), PhaseKind::Disperse);
        $children = collect(range(1, 30))->map(fn () => $this->node($childPhase, $roots->first()));
        $this->explored(...$children);
        $this->publishPotentialFixture($this->substrate);
        $context = new ParticipationContext($phase->fresh());
        $this->assertCount(30, $context->propositions->keys()->intersect($roots->pluck('id')));
        $plan = app(InputPlanService::class)->forParticipant($phase->cycle->participant);
        $this->assertEqualsCanonicalizing($roots->pluck('id')->all(), array_column($plan['write_options'], 'id'));
        $this->assertSame([], $plan['exhausted_alternatives']);
        foreach ($children as $child) {
            $this->assertStringNotContainsString($child->id, json_encode(app(ParticipantContextSerializer::class)->plan($plan)));
        }
    }

    public function test_explored_scope_never_crosses_a_horizon_or_hides_nodes_from_the_active_graph(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->explored($root);
        $horizon = $this->laterHorizonFixture($root->phase->cycle->contextHorizon);
        $opening = Proposition::findOrFail($horizon->opened_by_proposition_id);
        $viewer = $this->phase($opening, PhaseKind::Distinguish);
        $this->assertFalse(app(ParticipantExplorationService::class)->explored($root, $viewer));
        $this->assertTrue(Proposition::active()->whereKey($root->id)->exists());
        $plan = app(InputPlanService::class)->forPhase($viewer->cycle->participant, $viewer);
        $this->assertNotContains($root->id, array_column($plan['write_options'], 'id'));
    }

    public function test_order_context_retains_exhausted_opening_committal_without_actions(): void
    {
        [$commit, , $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($commit, $tragedy);
        $this->rest($terminal);
        $source = $commit->parents()->wherePivot('type', 'commit')->sole();
        $phase = $this->phase($source, PhaseKind::Order);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $write = collect($plan['exhausted_alternatives'])->firstWhere('id', $commit->id);
        $this->assertNotNull($write);
        $this->assertTrue($write['basin_exhausted']);
        $this->assertFalse($write['actionable']);
        $this->assertStringNotContainsString($commit->id, json_encode($plan['schema']));
        $packet = app(ParticipantContextSerializer::class)->inference([
            'participant' => $phase->cycle->participant->only(['id', 'callsign', 'designation']),
            'plan' => app(ParticipantContextSerializer::class)->plan($plan),
        ]);
        $historical = collect($packet['write']['propositions'])->firstWhere('id', $commit->id);
        $this->assertTrue($historical['basin_exhausted']);
        $this->assertFalse($historical['actionable']);
        $this->assertSame('What should persist?', $packet['write']['question']);
        $this->assertStringNotContainsString($commit->id, json_encode($packet['actions']));
    }

    public function test_outstanding_discovery_does_not_reopen_an_exhausted_committal(): void
    {
        [$commit, , $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($commit, $tragedy);
        $this->rest($terminal);
        $phase = $this->phase($commit, PhaseKind::Distinguish);
        $this->assertSame('required', app(PersonalAcquisitionService::class)->state($phase)['status']);
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($phase, $context));
        }
        $this->expectException(ConflictHttpException::class);
        app(OperationalInstrumentService::class)->execute($phase, $commit,
            ['kind' => 'proposition_search', 'limit' => 1]);
    }

    public function test_first_tragedy_keeps_explored_evidence_in_live_participant_context_and_graph(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->explore($root);
        $terminal = $this->phase($root);
        $this->measure($root, 1);
        $state = $this->rest($terminal);
        $participant = $terminal->cycle->participant->fresh();
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $this->assertSame([], $packet['read']['propositions']);
        $this->assertSame([$this->origin->id], array_column($packet['gate']['propositions'], 'id'));
        $this->assertContains($root->id, array_column($packet['write']['propositions'], 'id'));
        $this->assertArrayNotHasKey('lineage', $packet);
        $this->assertContains($root->id, array_column($state['plan']['write_options'], 'id'));
        $this->assertSame([], $state['plan']['exhausted_alternatives']);
        $workspace = app(ParticipationService::class)->workspaceFor($participant);
        $this->assertSame($state['plan']['schema'], $workspace['plan']['schema']);
        User::factory()->create();
        foreach (['active', 'exhausted', 'history'] as $view) {
            $ids = array_column($this->getJson('/api/substrates/'.$this->substrate->slug.'/propositions?view='.$view)->assertOk()->json('data'), 'id');
            $this->assertContains($root->id, $ids, 'The expanded exhausted view also includes live context');
            $this->assertSame($state['plan']['schema'], app(ParticipationService::class)->stateFor($participant)['plan']['schema']);
        }
    }

    private function explored(Proposition ...$nodes): void
    {
        foreach ($nodes as $node) {
            ExplorationHistory::record($node);
        }
    }
}
