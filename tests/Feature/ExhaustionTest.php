<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\ExplorationEventService;
use App\Services\PotentialChargeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ExhaustionFixture;
use Tests\Fixtures\ParticipationScaleFixture;
use Tests\TestCase;

/** Controlled regional/recovery components; committed coordinates are not end-to-end Commit evidence. */
class ExhaustionTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    private function tragicChild(): array
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, .5, true);
        $head = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->explore($head);
        $phase = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);
        $this->boundaryTragedyFixture($head);
        $this->rest($phase);

        return [$opening, $phase, Tragedy::sole()];
    }

    public function test_fresh_zero_potential_basin_remains_open_and_recovers_locally(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, .5, true);
        $fresh = $this->node($this->phase($opening, PhaseKind::Distinguish), $opening);
        $this->measure($fresh, 1);
        $selection = app(PotentialChargeService::class)->inspectRecoveryFrom($fresh);
        $this->assertSame($opening->id, $selection['selection']['proposition_id']);
        $this->assertSame(0, $selection['recovery_hops']);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_last_continuation_closes_its_committal_without_moving_other_participants(): void
    {
        [, $first, $other, $commit] = $this->exhaustedBranch();
        $before = $other->cycle->participant->fresh()->getAttributes();
        $propositions = Proposition::count();
        $this->boundaryTragedyFixture($first->gate);
        $this->rest($first);
        $this->assertDatabaseCount('tragedies', 1);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertSame($propositions, Proposition::count());
        $this->assertTrue(app(ExhaustionService::class)->unavailable($commit));
        $this->assertSame($before, $other->cycle->participant->fresh()->getAttributes());
        $this->assertNull($other->fresh()->completed_at);
        $this->assertDatabaseHas('propositions', ['id' => $this->origin->id]);
        $this->assertFalse(DB::table('origin_branches')->whereNotNull('pruned_at')->exists());
    }

    public function test_immediate_exhaustion_authorises_recorded_parent_recovery(): void
    {
        [$opening, $terminal] = $this->tragicChild();
        $horizon = $terminal->cycle->contextHorizon;
        $this->assertTrue(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon->parent));
        $inspection = app(PotentialChargeService::class)->inspectRecoveryFrom($terminal->gate);
        $this->assertSame(1, $inspection['recovery_hops']);
        $this->assertSame($this->origin->id, $inspection['selection']['proposition_id']);
        $this->assertDatabaseHas('propositions', ['id' => $opening->id]);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }

    public function test_stale_witness_cannot_create_tragedy_or_exhaust_a_fresh_sibling(): void
    {
        [, $phase, , $commit] = $this->exhaustedBranch();
        $proof = app(ExplorationEventService::class)->inspect($phase);
        $fresh = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now(), 'rest_reason' => 'no_remaining_potential'])->save();
        app(ExplorationEventService::class)->publish($phase, $proof);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('exploration_events', 0);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($fresh));
    }

    public function test_uncompleted_rest_witness_has_no_effect(): void
    {
        [, $phase] = $this->exhaustedBranch();
        app(ExplorationEventService::class)->publish($phase, app(ExplorationEventService::class)->inspect($phase));
        $this->assertDatabaseCount('exploration_events', 0);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_diagnostics_do_not_mutate_or_exhaust_an_actionable_phase(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $before = $this->history();
        $proof = app(ExplorationEventService::class)->inspect($phase);
        $this->assertSame('no_available_move', $proof['rest_reason']);
        $this->assertNull($proof['witness']);
        $this->assertSame($before, $this->history());
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
    }

    public function test_legacy_retirement_is_retained_without_controlling_new_semantics(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        DB::table('origin_branches')->where('root_proposition_id', $root->id)
            ->update(['pruned_at' => now(), 'legacy_retired_at' => now(), 'exhausted_at' => now()]);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($root));
        $audit = app(ExhaustionAuditService::class)->inspect($this->substrate->id);
        $this->assertTrue($audit['matches']);
        // Obsolete retirement markers cannot become terminal event authority.
        $this->assertDatabaseCount('terminal_encounters', 0);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_audit_accepts_immediate_exhaustion(): void
    {
        $this->tragicChild();
        $audit = app(ExhaustionAuditService::class)->inspect($this->substrate->id);
        $this->assertTrue($audit['matches'], json_encode($audit));
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
    }

    public function test_audit_preserves_terminal_proof_independently_of_rest_and_consumption(): void
    {
        [, $phase, $tragedy] = $this->tragicChild();
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id]);
        $audit = app(ExhaustionAuditService::class)->inspect($this->substrate->id);
        $this->assertTrue($audit['matches'], json_encode($audit));
        DB::table('cycles')->where('id', $phase->cycle_id)->update(['rest_reason' => 'no_available_move']);
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('commit_region_exhaustions')->update(['boundary_field' => '{"charge":0.2,"tension":0.8}']));
    }

    #[Group('query')]
    public function test_horizon_proof_does_not_grow_with_unrelated_historical_terrain(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, .5, true);
        $head = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
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
            $proof = app(ExplorationEventService::class)->inspect($phase->fresh());
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $counts[] = count($queries);
            $this->assertSame('no_remaining_potential', $proof['rest_reason']);
            $this->assertSame($head->id, $proof['position_id']);
            $this->assertNull($proof['witness'], 'An unrecorded personal arrival cannot establish exploration');
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update/i', $query['query']);
            }
        }
        $this->assertSame($counts[0], $counts[1]);
    }
}
