<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExhaustionService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class FinalAttemptExhaustionTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    private function firstFailure(): array
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, boundary: true);
        $head = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->explore($head);
        $phase = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);
        $this->rest($phase);

        return [$opening, $phase->cycle->contextHorizon, Tragedy::sole()];
    }

    private function exercise($opening, $tragedy): array
    {
        $source = $this->node($this->phase($opening, PhaseKind::Persist), $opening);
        $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $phase = $this->phase($source, PhaseKind::Constrain);
        $result = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $tragedy->receipt_id]);
        $this->explore($result);
        $terminal = $this->phase($result);
        $this->measure($source, 1);
        $this->measure($result, 1);

        return [$terminal, $result];
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

    public function test_legacy_pruning_and_old_exhaustion_do_not_control_new_semantics(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        DB::table('origin_branches')->where('root_proposition_id', $root->id)
            ->update(['pruned_at' => now(), 'legacy_retired_at' => now(), 'exhausted_at' => now()]);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($root));
    }
}
