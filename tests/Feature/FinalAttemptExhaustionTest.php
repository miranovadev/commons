<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ExhaustionService;
use App\Services\InputPlanService;
use App\Services\ParticipantHistoricalContext;
use App\Services\ReceiptContextService;
use App\Services\TragedyEvidenceService;
use App\Services\TragedyFinalAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\HorizonProofFixture;
use Tests\TestCase;

class FinalAttemptExhaustionTest extends TestCase
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
        $this->assertNull($horizon->fresh()->exhausted_at);
        $this->assertSame(1, Tragedy::count());
        $this->assertFalse(Receipt::findOrFail($tragedy->receipt_id)->consumption()->exists());
        $this->assertDatabaseCount('horizon_exhaustions', 0);
    }

    public function test_final_attempt_allowance_is_horizon_local_atomic_and_shared_once(): void
    {
        [$opening, , $tragedy] = $this->firstFailure();
        $phase = $this->phase($opening, PhaseKind::Distinguish);
        $service = app(TragedyFinalAttemptService::class);
        $this->assertTrue($service->available($phase));
        $this->assertFalse($service->available($this->phase($this->origin, PhaseKind::Distinguish)));
        $variant = $this->node($phase, $opening);
        try {
            DB::transaction(function () use ($service, $phase, $variant): void {
                $service->record($phase, $variant);
                throw new \DomainException('Rollback the contribution.');
            });
            $this->fail('The contribution should roll back.');
        } catch (\DomainException $exception) {
            $this->assertSame('Rollback the contribution.', $exception->getMessage());
        }
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
        $this->assertTrue($service->available($phase));
        DB::transaction(fn () => $service->record($phase, $variant));
        $this->assertDatabaseHas('tragedy_final_attempts', ['tragedy_id' => $tragedy->id, 'proposition_id' => $variant->id]);
        $this->assertFalse($service->available($phase));
        $this->assertFalse($service->available($this->phase($opening, PhaseKind::Distinguish)));
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id]);
    }

    public function test_consuming_tragedy_through_an_existing_slot_spends_the_unused_allowance(): void
    {
        [$opening, , $tragedy] = $this->firstFailure();
        $this->exercise($opening, $tragedy);
        $phase = $this->phase($opening, PhaseKind::Distinguish);
        $this->assertFalse(app(TragedyFinalAttemptService::class)->available($phase));
        $this->assertDatabaseCount('tragedy_final_attempts', 0);
    }

    public function test_exercised_tragedy_receipt_then_later_failure_exhausts_exactly_once(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        [$terminal] = $this->exercise($opening, $tragedy);
        $proof = app(ExhaustionService::class)->inspect($terminal);
        $this->assertSame('no_remaining_potential', $proof['rest_reason']);
        $this->rest($terminal);
        $this->assertNotNull($horizon->fresh()->exhausted_at);
        $this->assertTrue(app(ExhaustionService::class)->unavailable($opening));
        $this->assertDatabaseHas('propositions', ['id' => $opening->id]);
        $this->assertDatabaseHas('receipts', ['id' => $tragedy->receipt_id]);
        $this->assertDatabaseCount('horizon_exhaustions', 1);
        $historyPlan = app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal);
        $historicalReceipt = collect($historyPlan['receipts'])->firstWhere('id', $tragedy->receipt_id);
        $this->assertNull($historicalReceipt, 'Consumed unread evidence leaves the shared catalogue.');
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
        $this->assertDatabaseCount('horizon_exhaustions', 1);
        $this->assertNull($horizon->parent->fresh()->exhausted_at, 'Parent failure needs its own evidence-bearing proof');
        $this->assertDatabaseHas('propositions', ['id' => $this->origin->id]);
        $this->assertFalse(DB::table('origin_branches')->whereNotNull('pruned_at')->exists());
    }

    public function test_receipt_use_with_successful_positive_potential_continuation_keeps_horizon_alive(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        [$terminal, $result] = $this->exercise($opening, $tragedy);
        $this->measure($result, 0);
        $this->assertSame('no_available_move', app(ExhaustionService::class)->inspect($terminal)['rest_reason']);
        $this->rest($terminal);
        $this->assertNull($horizon->fresh()->exhausted_at);
        $this->assertDatabaseCount('horizon_exhaustions', 0);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($opening));
    }

    public function test_receipt_consumption_cannot_make_an_unrelated_later_failure_exhaust_the_horizon(): void
    {
        [$opening, $horizon, $tragedy] = $this->firstFailure();
        [$terminal, $result] = $this->exercise($opening, $tragedy);
        $unrelated = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->explore($unrelated);
        $other = $this->phase($unrelated);
        $this->measure($unrelated, 1);
        $proof = app(ExhaustionService::class)->inspect($other);
        $this->assertSame('no_remaining_potential', $proof['rest_reason']);
        $this->assertNull($proof['consumed_continuation_id']);
        $this->rest($other);
        $this->assertNull($horizon->fresh()->exhausted_at);
        $this->assertDatabaseCount('horizon_exhaustions', 0);
        $this->assertSame($result->id, app(ExhaustionService::class)->consumedContinuationFor($terminal));
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
        $this->assertNotNull($horizon->fresh()->exhausted_at);
        $parentHead = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $this->explore($parentHead);
        $parentRest = $this->phase($parentHead);
        $this->measure($parentHead, 1);
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
        $this->assertSame($opening->id, $context['evidence']['exhausted_committals'][0]['id']);
        $this->assertSame('available', $context['availability']);
        $this->assertDatabaseHas('propositions', ['id' => $opening->id]);
        $this->assertNull($horizon->parent->fresh()->exhausted_at);
        // The proof can continue to Origin, without deleting its exhausted child.
        [$originTerminal] = $this->exercise($this->origin, $parentTragedy);
        $this->rest($originTerminal);
        $this->assertNotNull($horizon->parent->fresh()->exhausted_at);
        $this->assertDatabaseCount('horizon_exhaustions', 2);
        $this->assertDatabaseHas('propositions', ['id' => $this->origin->id]);
        $this->assertDatabaseHas('propositions', ['id' => $opening->id]);
        $this->assertSame(1, DB::table('commitment_snapshots')->where('calculator_version', 'origin-bootstrap-v1')->count());
        $this->assertFalse(DB::table('origin_branches')->whereNotNull('pruned_at')->exists());
    }
}
