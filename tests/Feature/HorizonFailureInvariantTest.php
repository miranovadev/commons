<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\ExhaustionAuditService;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\PotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonProofFixture;
use Tests\Fixtures\ParticipationScaleFixture;
use Tests\TestCase;

class HorizonFailureInvariantTest extends TestCase
{
    use HorizonProofFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_zero_potential_never_exhausts_fresh_structure_without_rest(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $commit = $this->commitFrom($root, .5, true);
        $fresh = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $phase = $this->phase($fresh, PhaseKind::Persist);
        $this->measure($root, 1);
        $this->measure($fresh, 1);
        $this->assertSame($commit->id, app(PotentialChargeService::class)->inspectRecoveryFrom($fresh)['selection']['proposition_id']);
        $this->assertArrayHasKey('proposition.variant', app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase)['schema']['$defs']['actions']);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame(0, Tragedy::count());
    }

    public function test_first_failure_closes_only_local_expressive_provenance_not_committals_or_other_participants(): void
    {
        [$root, $first, $second, $commit, $head] = $this->exhaustedBranch();
        $before = $second->cycle->participant->fresh()->getAttributes();
        $history = $this->history();
        $this->rest($first);
        $this->assertSame(0, Tragedy::count(), 'Rest does not discover a catastrophe');
        $this->assertDatabaseHas('continuation_failures', ['proposition_id' => $head->id]);
        $this->assertDatabaseMissing('continuation_failures', ['proposition_id' => $commit->id]);
        $this->assertDatabaseMissing('continuation_failures', ['proposition_id' => $root->id]);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame($before, $second->cycle->participant->fresh()->getAttributes());
        $this->assertNull($second->fresh()->completed_at);
        foreach (['propositions', 'supersessions', 'commitment_snapshots'] as $table) {
            $this->assertSame(array_column($history[$table], 'id'), array_column($this->history()[$table], 'id'));
        }
        $this->assertFalse(DB::table('origin_branches')->whereNotNull('pruned_at')->exists());
    }

    public function test_stale_proof_cannot_publish_after_a_concurrent_field_change(): void
    {
        [, $phase, , $commit] = $this->exhaustedBranch();
        $proof = app(ExhaustionService::class)->inspect($phase);
        $sibling = $this->node($this->phase($commit, PhaseKind::Distinguish), $commit);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now(), 'rest_reason' => 'no_remaining_potential'])->save();
        app(ExhaustionService::class)->publish($phase, $proof);
        $this->assertDatabaseCount('horizon_failure_witnesses', 0);
        $this->assertSame(0, Tragedy::count());
        $this->assertFalse(app(ExhaustionService::class)->unavailable($sibling));
    }

    public function test_proof_requires_authoritative_reason_completed_rest_and_matching_identity(): void
    {
        [, $phase, $other] = $this->exhaustedBranch();
        $proof = app(ExhaustionService::class)->inspect($phase);
        app(ExhaustionService::class)->publish($phase, $proof);
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['rested_at' => now(), 'completed_at' => now(), 'rest_reason' => 'no_available_move'])->save();
        app(ExhaustionService::class)->publish($phase, $proof);
        app(ExhaustionService::class)->publish($other, $proof);
        $this->assertDatabaseCount('horizon_failure_witnesses', 0);
        $this->assertSame(0, Tragedy::count());
    }

    public function test_audit_validates_receipt_exercise_and_detects_corrupt_regional_proof(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        $audit = app(ExhaustionAuditService::class);
        $this->assertTrue($audit->inspect($this->substrate->id)['matches']);
        [$terminal] = $this->exercise($opening, $tragedy);
        $this->rest($terminal);
        $this->assertTrue($audit->inspect($this->substrate->id)['matches']);
        $this->artisan('commons:exhaustion-audit', ['substrate' => $this->substrate->id])->assertSuccessful();
        DB::table('commit_region_exhaustions')->where('context_horizon_id', $horizon->id)
            ->update(['terminal_proposition_id' => $this->origin->id]);
        $result = $audit->inspect($this->substrate->id);
        $this->assertFalse($result['matches']);
        $this->assertSame([$horizon->id], $result['horizon_mismatches']);
    }

    public function test_legacy_retirement_import_archives_history_without_actionability_effects(): void
    {
        [$root] = $this->exhaustedBranch();
        DB::table('origin_branches')->where('root_proposition_id', $root->id)->update(['pruned_at' => now()]);
        $this->artisan('commons:exhaustion-legacy-import', ['substrate' => $this->substrate->id, '--apply' => true])->assertSuccessful();
        $this->artisan('commons:exhaustion-legacy-import', ['substrate' => $this->substrate->id, '--apply' => true])->assertSuccessful();
        $this->assertFalse(app(ExhaustionService::class)->unavailable($root));
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame(0, Tragedy::count());
        $this->assertNotNull(Proposition::find($root->id));
    }

    #[Group('query')]
    public function test_failure_inspection_is_read_only_and_does_not_scan_unrelated_history(): void
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
            $proof = app(ExhaustionService::class)->inspect($phase->fresh());
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $counts[] = count($queries);
            $this->assertSame([$head->id], $proof['transitions']);
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/insert |update |delete |for update/i', $query['query']);
            }
        }
        $this->assertSame($counts[0], $counts[1]);
    }
}
