<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\LedgerService;
use App\Services\PotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ExhaustionFixture;
use Tests\Fixtures\ParticipationScaleFixture;
use Tests\TestCase;

/** Final-attempt proof replaces the retired immediate recursive-closure contract. */
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

    private function exhaustFinalAttempt(): array
    {
        [$opening, $first, $tragedy] = $this->tragicChild();
        $source = $this->node($this->phase($opening, PhaseKind::Persist), $opening);
        $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $phase = $this->phase($source, PhaseKind::Constrain);
        $result = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $tragedy->receipt_id]);
        $this->explore($result);
        $terminal = $this->phase($result);
        foreach (Proposition::whereHas('phase.cycle', fn ($query) => $query
            ->where('context_horizon_id', $terminal->cycle->context_horizon_id))->where('tier', 'expressive')->get() as $node) {
            $this->measure($node, 1);
        }
        $this->measure($source, 1);
        $this->measure($result, 1);
        $this->rest($terminal);

        return [$opening, $terminal, $tragedy];
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

    public function test_tragedy_does_not_close_the_opening_committal_or_move_other_participants(): void
    {
        [, $first, $other, $commit] = $this->exhaustedBranch();
        $before = $other->cycle->participant->fresh()->getAttributes();
        $propositions = Proposition::count();
        $this->boundaryTragedyFixture($first->gate);
        $this->rest($first);
        $this->assertDatabaseCount('tragedies', 1);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame($propositions, Proposition::count());
        $this->assertFalse(app(ExhaustionService::class)->unavailable($commit));
        $this->assertSame($before, $other->cycle->participant->fresh()->getAttributes());
        $this->assertNull($other->fresh()->completed_at);
        $this->assertDatabaseHas('propositions', ['id' => $this->origin->id]);
        $this->assertFalse(DB::table('origin_branches')->whereNotNull('pruned_at')->exists());
    }

    public function test_first_failure_stops_at_child_horizon_and_final_failure_recovers_to_parent(): void
    {
        [$opening, $terminal] = $this->exhaustFinalAttempt();
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
        $proof = app(ExhaustionService::class)->inspect($phase);
        $fresh = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now(), 'rest_reason' => 'no_remaining_potential'])->save();
        app(ExhaustionService::class)->publish($phase, $proof);
        $this->assertDatabaseCount('tragedies', 0);
        $this->assertDatabaseCount('continuation_failures', 0);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($fresh));
    }

    public function test_uncompleted_rest_witness_has_no_effect(): void
    {
        [, $phase] = $this->exhaustedBranch();
        app(ExhaustionService::class)->publish($phase, app(ExhaustionService::class)->inspect($phase));
        $this->assertDatabaseCount('horizon_failure_witnesses', 0);
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_diagnostics_do_not_mutate_or_exhaust_an_actionable_phase(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $before = $this->history();
        $proof = app(ExhaustionService::class)->inspect($phase);
        $this->assertSame('no_available_move', $proof['rest_reason']);
        $this->assertSame([], $proof['transitions']);
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
        $this->assertSame(1, $audit['legacy_holds']);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_audit_accepts_first_tragedy_without_exhaustion(): void
    {
        $this->tragicChild();
        $audit = app(ExhaustionAuditService::class)->inspect($this->substrate->id);
        $this->assertTrue($audit['matches'], json_encode($audit));
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_audit_accepts_exercised_final_attempt_and_detects_missing_consumption(): void
    {
        [, , $tragedy] = $this->exhaustFinalAttempt();
        $audit = app(ExhaustionAuditService::class)->inspect($this->substrate->id);
        $this->assertTrue($audit['matches'], json_encode($audit));
        DB::table('receipt_consumptions')->where('receipt_id', $tragedy->receipt_id)->delete();
        $audit = app(ExhaustionAuditService::class)->inspect($this->substrate->id);
        $this->assertFalse($audit['matches']);
        $this->assertSame([$tragedy->context_horizon_id], $audit['invalid_receipt_consumptions']);
    }

    public function test_audit_checks_the_retained_exhaustion_rest_witness(): void
    {
        [, $phase, $tragedy] = $this->exhaustFinalAttempt();
        DB::table('cycles')->where('id', $phase->cycle_id)->update(['rest_reason' => 'no_available_move']);
        $audit = app(ExhaustionAuditService::class)->inspect($this->substrate->id);
        $this->assertFalse($audit['matches']);
        $this->assertSame([], $audit['horizon_mismatches']);
        $this->assertSame([$phase->id], $audit['invalid_rest_witnesses']);
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
            $proof = app(ExhaustionService::class)->inspect($phase->fresh());
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $counts[] = count($queries);
            $this->assertSame('no_remaining_potential', $proof['rest_reason']);
            $this->assertSame([$head->id], $proof['transitions']);
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update/i', $query['query']);
            }
        }
        $this->assertSame($counts[0], $counts[1]);
    }
}
