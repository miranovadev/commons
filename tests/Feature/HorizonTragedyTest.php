<?php

namespace Tests\Feature;

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

    public function test_first_tragic_rest_creates_one_shared_receipt_and_recovers_without_exhaustion(): void
    {
        [, $phase] = $this->exhaustedBranch();
        $proof = app(ExhaustionService::class)->inspect($phase);
        $this->rest($phase);
        $tragedy = Tragedy::sole();
        $receipt = Receipt::findOrFail($tragedy->receipt_id);
        $this->assertSame($phase->cycle->context_horizon_id, $tragedy->context_horizon_id);
        $this->assertSame(RestReason::NoRemainingPotential, $phase->cycle->fresh()->rest_reason);
        $this->assertNull($phase->cycle->contextHorizon->fresh()->exhausted_at);
        $this->assertNull($receipt->instrument_execution_id, 'Tragedy is not a fabricated Instrument execution');
        $this->assertSame('tragedy', $receipt->type);
        $this->assertSame($this->origin->id, $phase->cycle->participant->fresh()->current_proposition_id);
        $this->assertTrue(app(HorizonReceiptService::class)->available($phase)->contains('id', $receipt->id));
        $this->assertSame($this->origin->id, $phase->cycle->fresh()->recovery_proposition_id);
        app(ExhaustionService::class)->publish($phase, $proof);
        $this->assertSame(1, Tragedy::count());
        $this->assertSame(1, Receipt::where('type', 'tragedy')->count());
        $this->assertArrayNotHasKey('statement', $receipt->metadata);
        $this->assertDatabaseHas('propositions', ['id' => $tragedy->opening_proposition_id]);
    }

    public function test_actionless_rest_does_not_create_tragedy(): void
    {
        $head = $this->node($this->phase($this->origin, \App\Enums\PhaseKind::Constrain), $this->origin);
        $phase = $this->phase($head);
        $this->measure($head, 1);
        $this->rest($phase);
        $this->assertSame(RestReason::NoAvailableMove, $phase->cycle->fresh()->rest_reason);
        $this->assertSame(0, Tragedy::count());
        $this->assertSame(0, Receipt::where('type', 'tragedy')->count());
    }

    public function test_nested_horizon_recovers_to_its_own_opening_committal(): void
    {
        $root = $this->node($this->phase($this->origin, \App\Enums\PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, boundary: true);
        $head = $this->node($this->phase($opening, \App\Enums\PhaseKind::Constrain), $opening);
        $this->explore($head);
        $phase = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);
        $this->rest($phase);
        $this->assertSame($opening->id, Tragedy::sole()->opening_proposition_id);
        $this->assertSame($opening->id, $phase->cycle->participant->fresh()->current_proposition_id);
        $this->assertNull($phase->cycle->contextHorizon->fresh()->exhausted_at);
        $this->assertFalse(app(ExhaustionService::class)->unavailable($opening));
    }
}
