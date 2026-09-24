<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\RestReason;
use App\Models\Receipt;
use App\Models\Tragedy;
use App\Services\ExhaustionService;
use App\Services\HorizonReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class HorizonTragedyTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_boundary_tragedy_has_one_shared_receipt_and_rest_uses_normal_ancestral_recovery(): void
    {
        [, $phase, , $commit] = $this->exhaustedBranch();
        $this->boundaryTragedyFixture($phase->gate);
        $proof = app(ExhaustionService::class)->inspect($phase);
        $this->rest($phase);
        $tragedy = Tragedy::sole();
        $receipt = Receipt::findOrFail($tragedy->receipt_id);
        $this->assertSame($phase->cycle->context_horizon_id, $tragedy->context_horizon_id);
        $this->assertSame(RestReason::NoRemainingPotential, $phase->cycle->fresh()->rest_reason);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($phase->cycle->contextHorizon));
        $this->assertNull($receipt->instrument_execution_id, 'Tragedy is not a fabricated Instrument execution');
        $this->assertSame('tragedy', $receipt->type);
        $this->assertSame($commit->id, $phase->cycle->participant->fresh()->current_proposition_id);
        $this->assertTrue(app(HorizonReceiptService::class)->catalogue($phase)->contains('id', $receipt->id));
        $this->assertNull($phase->cycle->fresh()->recovery_proposition_id, 'Tragedy cannot override lineage recovery.');
        app(ExhaustionService::class)->publish($phase, $proof);
        $this->assertSame(1, Tragedy::count());
        $this->assertSame(1, Receipt::where('type', 'tragedy')->count());
        $this->assertArrayNotHasKey('statement', $receipt->metadata);
        $this->assertDatabaseHas('propositions', ['id' => $tragedy->opening_proposition_id]);
    }

    public function test_actionless_rest_does_not_create_tragedy(): void
    {
        $head = $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);
        $phase = $this->phase($head);
        $this->measure($head, 1);
        $this->rest($phase);
        $this->assertSame(RestReason::NoAvailableMove, $phase->cycle->fresh()->rest_reason);
        $this->assertSame(0, Tragedy::count());
        $this->assertSame(0, Receipt::where('type', 'tragedy')->count());
    }

    public function test_nested_horizon_recovers_to_its_own_opening_committal(): void
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, boundary: true);
        $head = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->explore($head);
        $phase = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);
        $this->boundaryTragedyFixture($head);
        $this->rest($phase);
        $this->assertSame($opening->id, Tragedy::sole()->opening_proposition_id);
        $this->assertSame($opening->id, $phase->cycle->participant->fresh()->current_proposition_id);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($phase->cycle->contextHorizon));
        $this->assertFalse(app(ExhaustionService::class)->unavailable($opening));
    }
}
