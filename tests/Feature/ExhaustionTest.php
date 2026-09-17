<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Models\User;
use App\Services\ContextHorizonProjector;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\ReferencePotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExhaustionFixture;
use Tests\Fixtures\ParticipationScaleFixture;
use Tests\TestCase;

class ExhaustionTest extends TestCase
{
    use ExhaustionFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_fresh_origin_path_and_post_commit_horizon_are_not_exhausted_by_zero_potential(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root, .5, true);
        $fresh = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $phase = $this->phase($fresh, PhaseKind::Persist);
        $this->measure($root, 1);
        $this->measure($fresh, 1);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($fresh);
        $this->assertSame($commit->id, $inspection['selection']['proposition_id']);
        $this->assertSame(0, $inspection['recovery_hops']);
        $this->assertSame(0, DB::table('exhaustion_states')->count());
        $this->assertNull($phase->cycle->contextHorizon->fresh()->exhausted_at);
        $this->assertSame(0, Tragedy::count());
    }

    public function test_fresh_post_commit_sibling_vetoes_origin_exhaustion_and_is_recovered_locally(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root, .5, true);
        $dead = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $fresh = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $this->explore($dead);
        $terminal = $this->phase($dead);
        $this->measure($root, 1);
        $this->measure($dead, 1);
        $this->measure($fresh, 1);
        $proof = app(ExhaustionService::class)->inspect($terminal);
        $this->assertSame([], $proof['branches']);
        $this->assertSame([$dead->id], array_column($proof['transitions'], 'proposition_id'));
        $state = $this->rest($terminal);
        $this->assertSame($commit->id, $state['current_proposition_id']);
        $this->assertDatabaseHas('exhaustion_states', ['proposition_id' => $dead->id, 'kind' => 'excursion']);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $fresh->id]);
        $this->assertNull($terminal->cycle->contextHorizon->fresh()->exhausted_at);
        $this->assertSame(0, Tragedy::count());
        // Recovery is Phase 1. The exhausted Constrain position is not a
        // Distinguish alternative merely because both share a governing Commit.
        $this->assertSame([], $state['plan']['exhausted_alternatives']);
        $this->assertStringNotContainsString($dead->id, json_encode($state['plan']['schema']));
    }

    public function test_actual_terminal_exploration_exhausts_lineage_once_preserving_commits_and_other_participants(): void
    {
        [$root, $first, $second, $commit, $head] = $this->exhaustedBranch();
        $before = $second->cycle->participant->fresh()->getAttributes();
        $history = $this->history();
        $state = $this->rest($first);
        $this->assertSame($this->origin->id, $state['current_proposition_id']);
        $this->assertSame(1, Tragedy::count());
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
        $this->assertSame('exhaustion-v1', Tragedy::sole()->evidence['semantics']);
        $this->assertDatabaseHas('exhaustion_states', ['proposition_id' => $commit->id, 'kind' => 'basin']);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $commit->id, 'kind' => 'excursion']);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $this->origin->id]);
        $this->assertNull(DB::table('origin_branches')->where('root_proposition_id', $root->id)->value('pruned_at'));
        $this->assertSame($history, $this->history());
        $this->assertSame($before, $second->cycle->participant->fresh()->getAttributes());
        $this->assertNull($second->fresh()->completed_at);
        $this->assertSame(['participant.rest'], array_keys(app(InputPlanService::class)->forParticipant($second->cycle->participant)['schema']['$defs']['actions']));
        $count = DB::table('exhaustion_states')->count();
        $this->rest($second);
        $this->assertSame($count, DB::table('exhaustion_states')->count());
        $this->assertSame(1, Tragedy::count());
    }

    public function test_a_fresh_empty_child_basin_blocks_exhaustion_of_its_ancestry(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root, .5, true);
        $terminal = $this->phase($root);
        $this->measure($root, 1);
        $proof = app(ExhaustionService::class)->inspect($terminal);
        $this->assertSame([], $proof['transitions']);
        $this->assertSame([], $proof['branches']);
        $this->assertNull(app(ContextHorizonProjector::class)->forEntry($commit)->exhausted_at);
        $atBoundary = $this->phase($commit);
        $boundaryProof = app(ExhaustionService::class)->inspect($atBoundary);
        $this->assertSame([], $boundaryProof['transitions']);
        $this->assertSame([], $boundaryProof['branches']);
    }

    public function test_parent_sibling_stops_recovery_after_one_commit_boundary(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $a = $this->commitFrom($root, .5, true);
        $route = $this->node($this->phase($a, PhaseKind::Distinguish), $a);
        $b = $this->commitFrom($route, .4, true);
        $sibling = $this->node($this->phase($a, PhaseKind::Distinguish), $a);
        $head = $this->node($this->phase($b, PhaseKind::Constrain), $b);
        $this->explore($head);
        $terminal = $this->phase($head);
        $this->measure($sibling, 1);
        $this->measure($head, 1);
        $state = $this->rest($terminal);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($head);
        $this->assertSame($a->id, $state['current_proposition_id']);
        $this->assertSame(1, $inspection['recovery_hops']);
        $this->assertSame([$b->id, $a->id], array_column($inspection['searched_basins'], 'committal_proposition_id'));
        $this->assertNotNull(app(ContextHorizonProjector::class)->forEntry($b)->exhausted_at);
        $this->assertNull(app(ContextHorizonProjector::class)->forEntry($a)->exhausted_at);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $sibling->id]);
        $this->assertSame(0, Tragedy::count());
    }

    public function test_multicommit_recovery_visits_each_boundary_before_origin(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $a = $this->commitFrom($root, .5, true);
        $b = $this->commitFrom($this->node($this->phase($a, PhaseKind::Distinguish), $a), .4, true);
        $c = $this->commitFrom($this->node($this->phase($b, PhaseKind::Distinguish), $b), .3, true);
        $head = $this->node($this->phase($c, PhaseKind::Constrain), $c);
        $this->explore($head);
        $terminal = $this->phase($head);
        $this->measure($head, 1);
        $this->rest($terminal);
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($head);
        $this->assertSame([$c->id, $b->id, $a->id, $this->origin->id], array_column($inspection['searched_basins'], 'committal_proposition_id'));
        $this->assertSame(3, $inspection['recovery_hops']);
        $this->assertSame($this->origin->id, $inspection['selection']['proposition_id']);
        foreach ([$a, $b, $c] as $commit) {
            $this->assertNotNull(Proposition::find($commit->id));
            $this->assertDatabaseHas('exhaustion_states', ['proposition_id' => $commit->id, 'kind' => 'basin']);
        }
        $this->assertSame(1, Tragedy::count());
    }

    public function test_another_origin_child_stays_viable_and_new_top_level_work_can_begin(): void
    {
        [$root, $terminal] = $this->exhaustedBranch();
        $other = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->measure($other, .1);
        $state = $this->rest($terminal);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($other));
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
        $state = app(ParticipationService::class)->contribute($terminal->cycle->participant->fresh(), $state['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A new journey']);
        $this->assertNotSame($root->id, $state['current_proposition_id']);
        $this->assertDatabaseHas('origin_branches', ['root_proposition_id' => $state['current_proposition_id'], 'exhausted_at' => null]);
        $this->assertSame(1, Tragedy::count());
    }

    public function test_stale_proof_cannot_exhaust_a_concurrently_created_sibling(): void
    {
        [$root, $phase, , $commit] = $this->exhaustedBranch();
        $proof = app(ExhaustionService::class)->inspect($phase);
        $sibling = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        app(ExhaustionService::class)->publish($phase, $proof);
        $this->assertSame(0, DB::table('exhaustion_states')->count());
        $this->assertSame(0, Tragedy::count());
        $this->assertFalse(app(ExhaustionService::class)->unavailable($sibling));
    }

    public function test_proof_requires_rest_completion_and_matching_phase_identity(): void
    {
        [, $phase, $other] = $this->exhaustedBranch();
        $proof = app(ExhaustionService::class)->inspect($phase);
        app(ExhaustionService::class)->publish($phase, $proof);
        $other->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        app(ExhaustionService::class)->publish($other, $proof);
        $this->assertSame(0, DB::table('exhaustion_states')->count());
    }

    public function test_zero_potential_and_diagnostics_never_exhaust_an_actionable_phase(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $proof = app(ExhaustionService::class)->inspect($phase);
        $this->assertSame('actions_remain', $proof['reason']);
        $this->assertSame([], $proof['transitions']);
        $this->assertSame(0, DB::table('exhaustion_states')->count());
    }

    public function test_legacy_holds_are_distinct_from_proven_exhaustion_and_remain_inspectable(): void
    {
        [$root, $phase] = $this->exhaustedBranch();
        DB::table('origin_branches')->where('root_proposition_id', $root->id)->update(['pruned_at' => now(), 'legacy_retired_at' => now()]);
        $this->publishPotentialFixture($this->substrate);
        $this->assertTrue(app(ExhaustionService::class)->unavailable($root));
        $this->assertSame($this->origin->id, app(ReferencePotentialChargeService::class)->recoverFrom($phase->gate)['proposition']->id);
        $this->assertNotNull(Proposition::find($root->id));
        $this->assertSame(0, DB::table('exhaustion_states')->count());
        $this->assertSame(0, Tragedy::count());
        $this->assertSame($this->origin->id, app(PotentialChargeService::class)->recoverFrom($phase->gate)['proposition_id']);
    }

    public function test_observer_modes_preserve_field_state_and_direct_commit_inspection(): void
    {
        [$root, $phase, , $commit] = $this->exhaustedBranch();
        $this->rest($phase);
        $version = $this->substrate->fresh()->field_version;
        User::factory()->create();
        $prefix = '/api/substrates/'.$this->substrate->slug.'/propositions';
        $live = $this->getJson($prefix)->assertOk()->json('data');
        $compact = $this->getJson($prefix.'?view=exhausted')->assertOk()->json('data');
        $history = $this->getJson($prefix.'?view=history')->assertOk()->json('data');
        $this->assertNotContains($root->id, array_column($live, 'id'));
        $this->assertContains($root->id, array_column($compact, 'id'));
        $this->assertNotContains($commit->id, array_column($compact, 'id'));
        $this->assertContains($commit->id, array_column($history, 'id'));
        $this->assertSame(collect($live)->firstWhere('id', $this->origin->id)['field'], collect($history)->firstWhere('id', $this->origin->id)['field']);
        $this->getJson($prefix.'/'.$commit->id)->assertOk()
            ->assertJsonPath('data.exhaustion.exhausted', false)->assertJsonPath('data.exhaustion.basin_exhausted', true);
        $this->assertSame($version, $this->substrate->fresh()->field_version);
        $this->assertSame($this->origin->id, app(PotentialChargeService::class)->recoverFrom($phase->gate)['proposition_id']);
    }

    public function test_reference_audit_matches_then_detects_corrupt_internal_projection(): void
    {
        [, $phase, , $commit] = $this->exhaustedBranch();
        $this->rest($phase);
        $audit = app(ExhaustionAuditService::class);
        $result = $audit->inspect($this->substrate->id);
        $this->assertTrue($result['matches'], json_encode($result));
        $this->artisan('commons:exhaustion-audit', ['substrate' => $this->substrate->id])->assertSuccessful();
        DB::table('exhaustion_states')->where('proposition_id', $commit->id)->delete();
        $result = $audit->inspect($this->substrate->id);
        $this->assertFalse($result['matches']);
        $this->assertContains($commit->id, $result['missing']);
    }

    public function test_two_leaf_rests_propagate_once_only_after_every_sibling_exhausts(): void
    {
        [$root, $first, , $commit] = $this->exhaustedBranch();
        $sibling = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $this->explore($sibling);
        $second = $this->phase($sibling);
        $this->measure($sibling, 1);
        $this->rest($first);
        $this->assertSame(0, Tragedy::count());
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $commit->id]);
        $this->rest($second);
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
    }

    public function test_reconciliation_propagates_to_both_inputs_but_preserves_their_open_siblings(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root, .5, true);
        $left = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $right = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $head = $this->node($this->phase($left, PhaseKind::Constrain), $left);
        $edge = (new Supersession)->forceFill(['phase_id' => $head->phase_id,
            'source_proposition_id' => $right->id, 'target_proposition_id' => $head->id, 'type' => 'reconcile']);
        $edge->save();
        app(LedgerService::class)->record($edge);
        $sibling = $this->node($this->phase($right, PhaseKind::Constrain), $right);
        $this->explore($head);
        $first = $this->phase($head);
        $this->measure($head, 1);
        $this->rest($first);
        $this->assertDatabaseHas('exhaustion_states', ['proposition_id' => $left->id]);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $right->id]);
        $this->assertDatabaseMissing('exhaustion_states', ['proposition_id' => $commit->id]);
        $this->assertSame(0, Tragedy::count());
        $this->explore($sibling);
        $second = $this->phase($sibling);
        $this->measure($sibling, 1);
        $this->rest($second);
        $this->assertDatabaseHas('exhaustion_states', ['proposition_id' => $right->id]);
        $this->assertSame($root->id, Tragedy::sole()->root_proposition_id);
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
    }

    public function test_legacy_import_is_explicit_idempotent_and_never_claims_exhaustion(): void
    {
        [$root] = $this->exhaustedBranch();
        DB::table('origin_branches')->where('root_proposition_id', $root->id)->update(['pruned_at' => now()]);
        $this->artisan('commons:exhaustion-legacy-import', ['substrate' => $this->substrate->id])->assertSuccessful();
        $this->assertNull(DB::table('origin_branches')->where('root_proposition_id', $root->id)->value('legacy_retired_at'));
        $this->artisan('commons:exhaustion-legacy-import', ['substrate' => $this->substrate->id, '--apply' => true])->assertSuccessful();
        $this->assertTrue(app(ExhaustionService::class)->unavailable($root));
        $this->artisan('commons:exhaustion-legacy-import', ['substrate' => $this->substrate->id, '--apply' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('exhaustion_states')->count());
        $this->assertSame(0, Tragedy::count());
    }

    public function test_migration_preserves_old_retirement_without_inventing_exhaustion(): void
    {
        [$root] = $this->exhaustedBranch();
        $migration = require database_path('migrations/2026_09_17_000000_introduce_exhaustion_states.php');
        $migration->down();
        $at = now()->toDateTimeString();
        DB::table('origin_branches')->where('root_proposition_id', $root->id)->update(['pruned_at' => $at]);
        $migration->up();
        $this->assertDatabaseHas('origin_branches', ['root_proposition_id' => $root->id,
            'pruned_at' => $at, 'legacy_retired_at' => $at, 'exhausted_at' => null]);
        $this->assertSame(0, DB::table('exhaustion_states')->count());
        $this->assertSame(0, Tragedy::count());
        $this->assertNotNull(Proposition::find($root->id));
    }

    public function test_exhaustion_proof_does_not_grow_with_unrelated_historical_terrain(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root, .5, true);
        $head = $this->node($this->phase($commit, PhaseKind::Constrain), $commit);
        $this->explore($head);
        $phase = $this->phase($head);
        $this->measure($head, 1);
        $counts = [];
        foreach ([0, 1000] as $size) {
            if ($size) {
                ParticipationScaleFixture::addHistory($this->origin, $size);
                $this->publishPotentialFixture($this->substrate);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $start = hrtime(true);
            $proof = app(ExhaustionService::class)->inspect($phase->fresh());
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $counts[] = count($queries);
            $this->assertSame($root->id, $proof['branches'][0]['root_proposition_id']);
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update/i', $query['query']);
            }
            fwrite(STDERR, sprintf("\nEXHAUSTION history=%d queries=%d wall=%.2fms\n", $size, count($queries), (hrtime(true) - $start) / 1e6));
        }
        $this->assertSame($counts[0], $counts[1]);
    }
}
