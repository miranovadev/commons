<?php

namespace Tests\Feature;

use App\Contracts\ParticipantInference;
use App\Enums\PhaseKind;
use App\Jobs\WakeParticipant;
use App\Models\CommitmentSnapshot;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Models\User;
use App\Services\ContextHorizonProjector;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\OriginBranchService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use App\Services\TragedyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class BranchTragedyTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    private Proposition $origin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->substrate = (new Substrate)->forceFill(['slug' => 'tragedy', 'title' => 'Tragedy', 'visibility' => 'public', 'configuration' => []]);
        $this->substrate->save();
        $phase = $this->phase();
        $this->origin = $this->node($phase, null, 'committal');
        $this->snapshot($this->origin, .8, 'origin-bootstrap-v1');
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now()])->save();
        $this->measure($this->origin, .8);
    }

    public function test_complete_exhaustion_prunes_after_rest_without_moving_other_participants(): void
    {
        [$root, $first, $second] = $this->exhaustedBranch();
        $before = $second->cycle->participant->fresh()->getAttributes();
        $historical = $this->history();
        $state = app(ParticipationService::class)->contribute($first->cycle->participant, $first->id, ['action' => 'participant.rest']);
        $this->assertSame(1, Tragedy::count());
        $this->assertNotNull(DB::table('origin_branches')->where('root_proposition_id', $root->id)->value('pruned_at'));
        $this->assertNotNull($first->cycle->fresh()->rested_at);
        $this->assertSame($before, $second->cycle->participant->fresh()->getAttributes());
        $this->assertNull($second->fresh()->completed_at);
        $this->assertSame('ready', $state['status']);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
        $this->assertSame($historical, $this->history());
        $plan = app(InputPlanService::class)->forParticipant($second->cycle->participant);
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $packet = app(ParticipationService::class)->stateFor($second->cycle->participant)['plan'];
        foreach (['lineage', 'read_options', 'gate_options', 'write_options', 'receipts'] as $key) {
            $this->assertSame([], $packet[$key]);
        }
        // The second Rest is idempotent with respect to retirement and recovers normally.
        $state = app(ParticipationService::class)->contribute($second->cycle->participant, $second->id, ['action' => 'participant.rest']);
        $this->assertSame(1, Tragedy::count());
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
    }

    public function test_fresh_zero_potential_root_is_not_a_branch_tragedy_and_can_still_grow(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $terminal = $this->phase($root);
        $this->measure($root, 1);
        $proof = app(TragedyService::class)->inspect($terminal);
        $this->assertFalse($proof['branches'][0]['exhausted']);
        app(ParticipationService::class)->contribute($terminal->cycle->participant, $terminal->id, ['action' => 'participant.rest']);
        $this->assertSame(0, Tragedy::count());
        $this->assertNull(DB::table('origin_branches')->where('root_proposition_id', $root->id)->value('pruned_at'));
        $builder = $this->phase($root, PhaseKind::Persist);
        $state = app(ParticipationService::class)->contribute($builder->cycle->participant, $builder->id,
            ['action' => 'proposition.variant', 'statement' => 'The immature branch can grow']);
        $this->assertNotSame($root->id, $state['current_proposition_id']);
        $this->assertFalse(app(OriginBranchService::class)->pruned($root));
    }

    public function test_several_shallow_zero_potential_branches_are_not_retired(): void
    {
        foreach (range(1, 5) as $i) {
            $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
            $terminal = $this->phase($root);
            $this->measure($root, 1);
            app(ParticipationService::class)->contribute($terminal->cycle->participant, $terminal->id, ['action' => 'participant.rest']);
        }
        $this->assertSame(5, DB::table('origin_branches')->whereNull('pruned_at')->count());
        $this->assertSame(0, Tragedy::count());
    }

    public function test_zero_measured_potential_renews_origin_and_new_distinction_does_not_resurrect_history(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $participant = $phase->cycle->participant;
        app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        $recovery = app(PotentialChargeService::class)->recoverFrom($root);
        $this->assertSame('origin_renewal', $recovery['reason']);
        $this->assertSame(0.0, $recovery['raw_potential_charge']);
        $live = $participant->cycles()->whereNull('completed_at')->sole()->currentPhase;
        app(ParticipationService::class)->contribute($participant, $live->id,
            ['action' => 'proposition.variant', 'statement' => 'Fresh possibility after Tragedy']);
        $new = $participant->fresh()->position;
        $this->assertNotSame($root->id, $new->id);
        $this->assertFalse(app(OriginBranchService::class)->pruned($new));
        $this->assertTrue(app(OriginBranchService::class)->pruned($root));
        $this->assertSame(2, DB::table('origin_branches')->count());
    }

    public function test_origin_renewal_reopens_a_participants_previous_distinguish_opportunity(): void
    {
        $first = $this->phase($this->origin, PhaseKind::Distinguish);
        $participant = $first->cycle->participant;
        app(ParticipationService::class)->contribute($participant, $first->id,
            ['action' => 'proposition.variant', 'statement' => 'The first attempt']);
        $old = $participant->fresh()->position;
        $commit = $this->commitFrom($old);
        $head = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $participant->forceFill(['current_proposition_id' => $head->id])->save();
        $first->cycle->phases()->whereNull('completed_at')->update(['completed_at' => now()]);
        $terminal = (new Phase)->forceFill(['cycle_id' => $first->cycle_id, 'gate_proposition_id' => $head->id,
            'kind' => PhaseKind::Order, 'write_register' => 'canon', 'started_at' => now()]);
        $terminal->save();
        $this->measure($old, 1);
        $this->measure($head, 1);
        $state = app(ParticipationService::class)->contribute($participant, $terminal->id, ['action' => 'participant.rest']);
        $this->assertArrayHasKey('proposition.variant', $state['plan']['schema']['$defs']['actions']);
        app(ParticipationService::class)->contribute($participant, $state['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A genuinely new attempt']);
        $this->assertNotSame($old->id, $participant->fresh()->current_proposition_id);
        $this->assertTrue(app(OriginBranchService::class)->pruned($old));
    }

    public function test_viable_sibling_prevents_branch_exhaustion_even_if_the_resting_path_is_exhausted(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $terminal = $this->node($this->phase($root), $root);
        $phase = $this->phase($terminal);
        $sibling = $this->node($this->phase($root), $root);
        $this->measure($root, 1);
        $this->measure($terminal, 1);
        $this->measure($sibling, 0);
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertFalse($proof['branches'][0]['exhausted']);
        $this->assertSame($this->origin->id, app(PotentialChargeService::class)->recoverFrom($terminal)['proposition']->id ?? null);
        $this->assertSame(0, Tragedy::count());
    }

    public function test_an_independent_live_origin_branch_is_preserved_and_selected_before_renewal(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $otherRoot = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $other = $this->commitFrom($otherRoot, .5);
        $this->measure($otherRoot, 1);
        $opportunity = $this->node($this->phase($other), $other);
        $this->measure($root, 1);
        $this->measure($opportunity, 0);
        $state = app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertTrue(app(OriginBranchService::class)->pruned($root));
        $this->assertFalse(app(OriginBranchService::class)->pruned($other));
        $this->assertSame($other->id, $state['current_proposition_id']);
    }

    public function test_stale_exhaustion_proof_cannot_retire_new_viable_work(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $before = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($before['branches'][0]['exhausted']);
        $commit = $this->node($this->phase($root), $root, 'committal');
        $this->snapshot($commit, .5);
        $opportunity = $this->node($this->phase($commit), $commit);
        $this->measure($root, 1);
        $this->measure($opportunity, 0);
        $this->assertNotSame($before['field_version'], (int) $this->substrate->fresh()->field_version);
        DB::transaction(function () use ($phase, $before): void {
            Substrate::whereKey($this->substrate->id)->lockForUpdate()->firstOrFail();
            app(TragedyService::class)->retire($phase, $before);
        });
        $this->assertSame(0, Tragedy::count());
        $after = app(TragedyService::class)->inspect($phase);
        // This descendant region must be checked even though the exhausted path
        // from Origin passes through the resting node.
        $this->assertFalse($after['branches'][0]['exhausted']);
    }

    public function test_potential_on_the_exhausted_rest_path_does_not_leave_a_healthy_branch_stranded(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $this->measure($root, 0);
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($proof['branches'][0]['exhausted']);
        $state = app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame(1, Tragedy::count());
        $this->assertSame('ready', $state['status']);
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
    }

    public function test_a_formerly_valid_action_is_rejected_after_pruning_then_only_rest_is_exposed(): void
    {
        [$root, $first] = $this->exhaustedBranch();
        $head = $first->gate;
        $other = $this->phase($head, PhaseKind::Disperse);
        $this->measure($root, 1);
        $schema = app(InputPlanService::class)->forParticipant($other->cycle->participant)['schema'];
        $this->assertArrayHasKey('proposition.variant', $schema['$defs']['actions']);
        app(ParticipationService::class)->contribute($first->cycle->participant, $first->id, ['action' => 'participant.rest']);
        try {
            app(ParticipationService::class)->contribute($other->cycle->participant, $other->id,
                ['action' => 'proposition.variant', 'statement' => 'Stale proposal'], $schema);
            $this->fail('A stale contribution must fail.');
        } catch (ConflictHttpException) {
            $this->assertNull($other->fresh()->completed_at);
            $this->assertSame($head->id, $other->cycle->participant->fresh()->current_proposition_id);
        }
    }

    public function test_active_graph_hides_retired_branch_while_history_and_direct_inspection_keep_it(): void
    {
        User::factory()->create();
        [$root, $phase] = $this->exhaustedBranch();
        app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $url = '/api/substrates/'.$this->substrate->slug.'/propositions';
        $active = $this->getJson($url)->assertOk()->json('data');
        $this->assertNotContains($root->id, array_column($active, 'id'));
        $history = $this->getJson($url.'?history=1')->assertOk()->json('data');
        $this->assertContains($root->id, array_column($history, 'id'));
        $this->getJson($url.'/'.$root->id)->assertOk()->assertJsonPath('data.id', $root->id);
    }

    public function test_diagnostics_are_read_only_and_retirement_is_constant_work_inside_the_write_lock(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now(), 'rested_at' => now()])->save();
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($proof['stable']);
        foreach ($sql as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b|for update/i', $query);
        }
        $sql = [];
        DB::transaction(function () use ($phase, $proof): void {
            Substrate::whereKey($this->substrate->id)->lockForUpdate()->firstOrFail();
            app(TragedyService::class)->retire($phase, $proof);
        });
        $this->assertLessThanOrEqual(12, count($sql));
        $this->assertSame(1, Tragedy::count());
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
    }

    public function test_shared_descendants_remain_active_and_membership_backfill_preserves_causal_branches(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        $other = $this->node($this->phase($this->origin), $this->origin);
        $shared = $this->node($this->phase($root), $root);
        $edge = (new Supersession)->forceFill(['phase_id' => $shared->phase_id,
            'source_proposition_id' => $other->id, 'target_proposition_id' => $shared->id, 'type' => 'reconcile']);
        $edge->save();
        app(LedgerService::class)->record($edge);
        $descendant = $this->node($this->phase($shared), $shared);
        $before = DB::table('origin_branch_memberships')->orderBy('root_proposition_id')->orderBy('proposition_id')->get()->toJson();
        $migration = require database_path('migrations/2026_09_16_020000_create_origin_branches.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, DB::table('origin_branch_memberships')->orderBy('root_proposition_id')->orderBy('proposition_id')->get()->toJson());
        foreach ([$root, $other, $shared, $descendant] as $node) {
            $this->measure($node, 1);
        }
        app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertTrue(app(OriginBranchService::class)->pruned($root));
        foreach ([$other, $shared, $descendant] as $node) {
            $this->assertFalse(app(OriginBranchService::class)->pruned($node));
        }
        $this->assertSame(1, Tragedy::count());
        $continued = $this->node($this->phase($descendant), $descendant);
        $this->assertSame([$other->id], DB::table('origin_branch_memberships')
            ->where('proposition_id', $continued->id)->pluck('root_proposition_id')->all());
    }

    public function test_missing_branch_projections_are_observed_in_a_batched_read_without_repairs(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        for ($i = 0; $i < 25; $i++) {
            $this->node($this->phase($root), $root);
        }
        DB::table('proposition_measurements')->update(['calculator_version' => null]);
        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($proof['stable']);
        $this->assertLessThan(300, count($sql), 'Branch observation must share one batched measurement context.');
        foreach ($sql as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b|for update/i', $query);
        }
    }

    public function test_commit_makes_a_lineage_mature_but_cannot_retire_it_without_rest(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root);
        $phase = $this->phase($commit, PhaseKind::Distinguish);
        $this->measure($root, 1);
        $proof = app(TragedyService::class)->inspect($phase);
        $this->assertTrue($proof['branches'][0]['has_commit']);
        $this->assertSame($commit->id, $proof['branches'][0]['earliest_commit_id']);
        app(TragedyService::class)->retire($phase, $proof);
        $this->assertSame(0, Tragedy::count());
        $this->assertFalse(app(OriginBranchService::class)->pruned($root));
        $this->assertArrayHasKey('proposition.variant', app(InputPlanService::class)->forParticipant($phase->cycle->participant)['schema']['$defs']['actions']);
    }

    public static function commitBoundaries(): array
    {
        return ['same Horizon' => [false], 'Domain boundary' => [true]];
    }

    #[DataProvider('commitBoundaries')]
    public function test_later_committed_region_exhaustion_recovers_to_a_viable_earliest_commit(bool $boundary): void
    {
        [$root, , , $first] = $this->exhaustedBranch();
        $opportunity = $this->node($this->phase($first, PhaseKind::Distinguish), $first);
        $second = $this->commitFrom($first, $boundary ? -.5 : .5, boundary: $boundary);
        $head = $this->node($this->phase($second, PhaseKind::Constrain), $second);
        $phase = $this->phase($head);
        $this->measure($opportunity, 0);
        $this->measure($head, 1);
        $proof = app(TragedyService::class)->inspect($phase)['branches'][0];
        $this->assertFalse($proof['exhausted']);
        $this->assertSame('viable_ancestor', $proof['reason']);
        $this->assertSame($first->id, $proof['earliest_commit_id']);
        $this->assertSame(.5, $proof['earliest_commit_potential']);
        $this->assertCount(2, $proof['committed_regions']);
        $state = app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame($first->id, $state['current_proposition_id']);
        $this->assertSame(0, Tragedy::count());
        $this->assertFalse(app(OriginBranchService::class)->pruned($root));
    }

    public function test_exhaustion_bubbles_through_nested_domains_to_the_earliest_commit_without_requiring_zero_origin_potential(): void
    {
        [$root, , , $first] = $this->exhaustedBranch();
        $second = $this->commitFrom($first, -.5, boundary: true);
        $third = $this->commitFrom($second, .5, boundary: true);
        $head = $this->node($this->phase($third, PhaseKind::Constrain), $third);
        $phase = $this->phase($head);
        $this->measure($root, 0); // Origin remains numerically viable before the first Commit.
        $this->measure($head, 1);
        $this->assertGreaterThan(0, DB::table('potential_entries')->where('proposition_id', $this->origin->id)->value('raw_potential_charge'));
        $proof = app(TragedyService::class)->inspect($phase)['branches'][0];
        $this->assertTrue($proof['exhausted']);
        $this->assertSame('earliest_commit_exhausted', $proof['reason']);
        $this->assertSame($first->id, $proof['earliest_commit_id']);
        $this->assertSame(0.0, $proof['earliest_commit_potential']);
        $this->assertCount(3, $proof['committed_regions']);
        $history = $this->history();
        app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
        $this->assertSame($head->id, Tragedy::sole()->terminal_proposition_id);
        $this->assertNotSame($root->id, Tragedy::sole()->terminal_proposition_id);
        $this->assertSame($history, $this->history());
        $this->assertFalse(app(OriginBranchService::class)->pruned($this->origin));
    }

    public function test_a_terminal_commit_is_not_excluded_from_its_own_exhaustion_checkpoint(): void
    {
        [$root, , , $commit] = $this->exhaustedBranch();
        $opportunity = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $phase = $this->phase($commit);
        $this->measure($opportunity, 0);
        $proof = app(TragedyService::class)->inspect($phase)['branches'][0];
        $this->assertFalse($proof['exhausted']);
        $this->assertSame(.5, $proof['earliest_commit_potential']);
        $this->assertSame('viable_ancestor', $proof['reason']);
    }

    public function test_a_commit_elsewhere_in_the_branch_does_not_mature_an_uncommitted_terminal_path(): void
    {
        [$root] = $this->exhaustedBranch();
        $phase = $this->phase($root);
        $proof = app(TragedyService::class)->inspect($phase)['branches'][0];
        $this->assertFalse($proof['has_commit']);
        $this->assertSame('immature_branch', $proof['reason']);
        $phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        app(TragedyService::class)->retire($phase, app(TragedyService::class)->inspect($phase));
        $this->assertFalse(app(OriginBranchService::class)->pruned($root));
        $this->assertSame(0, Tragedy::count());
    }

    public function test_a_committal_label_without_a_commit_snapshot_is_not_maturity(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $uncommitted = $this->node($this->phase($root), $root, 'committal');
        $phase = $this->phase($root);
        $phase->forceFill(['gate_proposition_id' => $uncommitted->id])->save();
        $this->measure($root, 1);
        $this->measure($uncommitted, 1);
        $proof = app(TragedyService::class)->inspect($phase)['branches'][0];
        $this->assertSame('immature_branch', $proof['reason']);
        $this->assertFalse($proof['exhausted']);
    }

    public function test_the_earliest_commit_can_be_a_domain_boundary(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $first = $this->commitFrom($root, -.5, boundary: true);
        $head = $this->node($this->phase($first, PhaseKind::Constrain), $first);
        $phase = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);
        $proof = app(TragedyService::class)->inspect($phase)['branches'][0];
        $this->assertTrue($proof['exhausted']);
        $this->assertSame($first->id, $proof['earliest_commit_id']);
        $this->assertSame($phase->cycle->context_horizon_id, $proof['earliest_commits'][0]['context_horizon_id']);
        app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
    }

    public function test_missing_earliest_commit_projection_never_proves_exhaustion(): void
    {
        [$root, $phase, , $commit] = $this->exhaustedBranch();
        $row = DB::table('potential_region_states')->where('context_horizon_id', $phase->cycle->context_horizon_id)->first();
        $local = json_decode($row->local_state, true);
        unset($local['entries'][$commit->id]);
        DB::table('potential_region_states')->where('context_horizon_id', $row->context_horizon_id)->update(['local_state' => json_encode($local)]);
        $proof = app(TragedyService::class)->inspect($phase)['branches'][0];
        $this->assertFalse($proof['exhausted']);
        $this->assertSame('incomplete_projection', $proof['reason']);
        $this->assertNull($proof['earliest_commit_potential']);
    }

    public function test_origin_branch_roots_are_expressive_children_not_direct_committals(): void
    {
        $invalid = $this->node($this->phase($this->origin), $this->origin, 'committal');
        $this->snapshot($invalid, .5);
        $this->assertDatabaseMissing('origin_branches', ['root_proposition_id' => $invalid->id]);
    }

    public function test_repeated_wakes_cannot_create_a_graveyard_of_uncommitted_branches(): void
    {
        config()->set('participation.max_actions_per_wake', 1);
        $inference = \Mockery::mock(ParticipantInference::class);
        $inference->shouldReceive('contribute')->andReturnUsing(function ($connection, $state): array {
            $actions = $state['plan']['schema']['$defs']['actions'];
            if (isset($actions['proposition.variant'])) {
                return ['action' => 'proposition.variant', 'statement' => 'A new shallow branch'];
            }
            $this->assertArrayHasKey('participant.rest', $actions);

            return ['action' => 'participant.rest'];
        });
        $this->app->instance(ParticipantInference::class, $inference);
        $connections = [];
        foreach (range(1, 3) as $i) {
            $phase = $this->phase($this->origin, PhaseKind::Distinguish);
            $connection = (new Connection)->forceFill(['provider' => 'openai', 'name' => 'Wake fixture',
                'configuration' => ['model' => 'test'], 'credentials' => ['api_key' => 'test']]);
            $connection->save();
            $linked = (new ParticipantConnection)->forceFill(['participant_id' => $phase->cycle->participant_id,
                'connection_id' => $connection->id, 'is_enabled' => true,
                'configuration' => ['trigger' => ['mode' => 'interval', 'interval_minutes' => 1]]]);
            $linked->save();
            $connections[] = $linked;
            $this->app->call([new WakeParticipant($linked->id), 'handle']);
            $root = $phase->cycle->participant->fresh()->position;
            $this->measure($root, 1);
            $this->assertSame(0.0, (float) DB::table('potential_branch_regions')->where('root_proposition_id', $root->id)->max('remaining_potential'));
        }
        foreach (range(1, 12) as $wake) {
            foreach ($connections as $linked) {
                $this->app->call([new WakeParticipant($linked->id), 'handle']);
            }
        }
        $this->assertSame(0, Tragedy::count());
        $this->assertSame(3, DB::table('origin_branches')->whereNull('pruned_at')->count());
        $this->assertSame(0, DB::table('origin_branches')->whereNotNull('pruned_at')->count());
    }

    private function exhaustedBranch(): array
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root);
        $head = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $first = $this->phase($head);
        $second = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);

        return [$root, $first, $second, $commit, $head];
    }

    private function commitFrom(Proposition $parent, float $tension = .5, bool $boundary = false): Proposition
    {
        $source = $this->node($this->phase($parent, PhaseKind::Bias), $parent);
        $this->measure($source, 1);
        $commit = $this->node($this->phase($source), $source, 'committal');
        $this->snapshot($commit, $tension);
        if ($boundary) {
            $snapshot = $commit->commitmentSnapshot;
            $snapshot->forceFill(['direction_after' => $snapshot->direction_before->opposite()])->save();
            app(ContextHorizonProjector::class)->apply($snapshot);
        }
        $this->publishPotentialFixture($this->substrate);

        return $commit;
    }

    private function phase(?Proposition $gate = null, PhaseKind $kind = PhaseKind::Order): Phase
    {
        $participant = (new Participant)->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => 'p-'.str()->ulid(), 'current_proposition_id' => $gate?->id]);
        $participant->save();
        $horizon = $gate === null ? app(ContextHorizonProjector::class)->currentFor($this->substrate)
            : ($gate->tier === 'committal' ? app(ContextHorizonProjector::class)->forEntry($gate) : $gate->phase->cycle->contextHorizon);
        $cycle = (new Cycle)->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id, 'sequence' => 1]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'gate_proposition_id' => $gate?->id,
            'kind' => $kind, 'write_register' => $horizon->domain->registerFor($kind), 'started_at' => now()]);
        $phase->save();

        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);

        return $phase;
    }

    private function node(Phase $phase, ?Proposition $parent, string $tier = 'expressive'): Proposition
    {
        $node = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => $tier, 'statement' => 'Branch fixture']);
        $node->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => $tier === 'committal' ? 'commit' : 'variant']);
            $edge->save();
            app(LedgerService::class)->record($edge);
        }

        return $node;
    }

    private function snapshot(Proposition $node, float $tension, string $version = 'tragedy-fixture'): void
    {
        (new CommitmentSnapshot)->forceFill(['proposition_id' => $node->id, 'substrate_id' => $this->substrate->id,
            'ledger_entry_id' => $node->ledgerEntry->id,
            'supersession_id' => Supersession::where('target_proposition_id', $node->id)->where('type', 'commit')->value('id'), 'correspondence' => $tension, 'chirality' => $tension, 'closure' => $tension,
            'prominence' => (1 + $tension) / 2, 'turbulence' => (1 - $tension) / 2, 'tension' => $tension,
            'direction_before' => $node->phase->cycle->contextHorizon->domain,
            'direction_after' => $node->phase->cycle->contextHorizon->domain,
            'calculator_version' => $version, 'evaluated_at' => now()])->save();
    }

    private function measure(Proposition $node, float $value): void
    {
        app(PropositionMeasurementService::class)->project($node, $value, $value, $value);
        app(PotentialProjectionService::class)->rebuild($this->substrate->id, false);
    }

    private function history(): array
    {
        return collect(['propositions', 'supersessions', 'attestations', 'receipts', 'commitment_snapshots', 'context_horizons', 'substrate_ledger_entries'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy($table === 'commitment_snapshots' ? 'proposition_id' : 'id')->get()->map(fn ($row) => (array) $row)->all()])->all();
    }
}
