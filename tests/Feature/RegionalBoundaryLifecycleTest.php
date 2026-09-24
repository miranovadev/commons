<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\ParticipantHistoricalContext;
use App\Services\ReceiptContextService;
use App\Services\TragedyEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HorizonProofFixture;
use Tests\TestCase;

class RegionalBoundaryLifecycleTest extends TestCase
{
    use HorizonProofFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_repeated_failure_before_receipt_use_keeps_horizon_open(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        $next = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->explore($next);
        $phase = $this->phase($next);
        $this->measure($next, 1);
        $this->rest($phase);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertSame(1, Tragedy::count());
        $this->assertFalse(Receipt::findOrFail($tragedy->receipt_id)->consumption()->exists());
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
    }

    public function test_second_fresh_boundary_exhausts_exactly_once_without_receipt_use(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $proof = app(ExhaustionService::class)->inspect($terminal);
        $this->assertSame('no_available_move', $proof['rest_reason']);
        $this->rest($terminal);
        $this->assertTrue(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertTrue(app(ExhaustionService::class)->unavailable($opening));
        $this->assertDatabaseHas('propositions', ['id' => $opening->id]);
        $this->assertDatabaseHas('receipts', ['id' => $tragedy->receipt_id]);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $historyPlan = app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal);
        $historicalReceipt = collect($historyPlan['receipts'])->firstWhere('id', $tragedy->receipt_id);
        $this->assertNotNull($historicalReceipt, 'The unused warrant remains visible as history, without authority.');
        $this->assertSame(['participant.rest'], array_keys($historyPlan['schema']['$defs']['actions']));
        $parentSource = $opening->parents()->wherePivot('type', 'commit')->sole();
        $observer = $this->phase($parentSource, PhaseKind::Order);
        $history = app(ParticipantHistoricalContext::class)->forPhase($observer);
        $retained = collect($history)->firstWhere('id', $opening->id);
        $this->assertNotNull($retained);
        $this->assertTrue($retained['basin_exhausted']);
        $this->assertFalse($retained['actionable']);
        $this->assertSame('write', $retained['role']);
        app(ExhaustionService::class)->publish($terminal, $proof);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon->parent), 'Parent failure needs its own evidence-bearing proof');
        $this->assertDatabaseHas('propositions', ['id' => $this->origin->id]);
        $this->assertFalse(DB::table('origin_branches')->whereNotNull('pruned_at')->exists());
    }

    public function test_positive_charge_after_tragedy_and_later_rest_keep_region_open(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        $gate = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $phase = $this->phase($gate, PhaseKind::Disperse);
        $this->resolveExpressiveFieldFixture(.8);
        $result = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Positive Charge after catastrophe']);
        $this->explore($result);
        $terminal = $this->phase($result);
        $this->rest($terminal);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id]);
    }

    public function test_legacy_pruning_and_old_exhaustion_do_not_control_new_semantics(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        DB::table('origin_branches')->where('root_proposition_id', $root->id)
            ->update(['pruned_at' => now(), 'legacy_retired_at' => now(), 'exhausted_at' => now()]);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($root));
    }

    public function test_child_exhaustion_enriches_parent_tragedy_with_ids_and_loads_statements_locally(): void
    {
        [$opening, $horizon, $childTragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $childTragedy);
        $this->rest($terminal);
        $this->assertTrue(app(ExhaustionService::class)->boundaryExhausted($horizon));
        $parentHead = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $this->explore($parentHead);
        $parentRest = $this->phase($parentHead);
        $this->measure($parentHead, 1);
        $this->boundaryTragedyFixture($parentHead);
        $this->rest($parentRest);
        $parentTragedy = Tragedy::where('context_horizon_id', $horizon->parent_context_horizon_id)->sole();
        $receipt = Receipt::findOrFail($parentTragedy->receipt_id);
        $this->assertSame([$opening->id], $receipt->metadata['exhausted_committal_ids']);
        $this->assertStringNotContainsString($opening->statement, json_encode($receipt->metadata));
        $evidence = app(TragedyEvidenceService::class)->describe($receipt);
        $this->assertSame($opening->statement, $evidence['exhausted_committals'][0]['statement']);
        $this->assertSame($horizon->id, $evidence['exhausted_committals'][0]['exhausted_horizon_id']);
        $this->assertSame(7, $evidence['exhausted_committals'][0]['phase_number']);
        $viewer = $this->phase($this->origin, PhaseKind::Distinguish);
        $context = app(ReceiptContextService::class)->forPhase($viewer)->firstWhere('id', $receipt->id);
        $this->assertSame($opening->id, $context['warrant']['exhausted_committals'][0]['id']);
        $this->assertSame('available', $context['availability']);
        $this->assertDatabaseHas('propositions', ['id' => $opening->id]);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($horizon->parent));
        // The proof can continue to Origin, without deleting its exhausted child.
        [$originTerminal] = $this->exercise($this->origin, $parentTragedy);
        $this->rest($originTerminal);
        $this->assertTrue(app(ExhaustionService::class)->boundaryExhausted($horizon->parent));
        $this->assertDatabaseCount('commit_region_exhaustions', 2);
        $this->assertDatabaseHas('propositions', ['id' => $this->origin->id]);
        $this->assertDatabaseHas('propositions', ['id' => $opening->id]);
        $this->assertSame(1, DB::table('commitment_snapshots')->where('calculator_version', 'origin-bootstrap-v1')->count());
        $this->assertFalse(DB::table('origin_branches')->whereNotNull('pruned_at')->exists());
    }
}
