<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Receipt;
use App\Services\HorizonReceiptService;
use App\Services\InstrumentExecutionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class HorizonReceiptLifecycleTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    private function observation(): array
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $source = $this->node($phase, $this->origin);
        $service = app(InstrumentExecutionService::class);
        $execution = $service->start($phase, $source, 'citation', $this->origin->id);
        $receipt = $service->complete($execution, $source);

        return [$source, $receipt, $execution];
    }

    public function test_instrument_generates_one_shared_horizon_receipt_and_cannot_complete_twice(): void
    {
        [$source, $receipt, $execution] = $this->observation();
        $other = $this->phase($this->origin, PhaseKind::Distinguish);
        $this->assertNotSame($source->phase->cycle->participant_id, $other->cycle->participant_id);
        $this->assertSame($other->cycle->context_horizon_id, $receipt->context_horizon_id);
        $this->assertSame([$receipt->id], app(HorizonReceiptService::class)->available($other)->modelKeys());
        $this->assertSame(1, Receipt::where('instrument_execution_id', $execution->id)->count());
        $this->expectException(DomainException::class);
        app(InstrumentExecutionService::class)->complete($execution, $source);
    }

    public function test_one_use_attachment_preserves_receipt_and_is_visible_to_every_participant(): void
    {
        [$source, $receipt] = $this->observation();
        $phase = $this->phase($source, PhaseKind::Constrain);
        $target = $this->node($phase, $source);
        $service = app(HorizonReceiptService::class);
        $original = $receipt->getAttributes();
        DB::transaction(fn () => $service->consume($phase, $source, $target, $receipt->id));
        $this->assertEquals($original, $receipt->fresh()->getAttributes());
        $this->assertTrue($service->available($source->phase)->isEmpty());
        $this->assertSame($target->id, $receipt->fresh()->consumption->proposition_id);
        $this->expectException(DomainException::class);
        DB::transaction(fn () => $service->consume($phase, $source, $target, $receipt->id));
    }

    public function test_classification_failure_rolls_back_consumption_and_evidence_link(): void
    {
        [$source, $receipt] = $this->observation();
        $phase = $this->phase($source, PhaseKind::Constrain);
        $target = $this->node($phase, $source);
        try {
            DB::transaction(function () use ($phase, $source, $target, $receipt): void {
                app(HorizonReceiptService::class)->consume($phase, $source, $target, $receipt->id);
                throw new DomainException('Classification rejected');
            });
            $this->fail('Expected rollback');
        } catch (DomainException $exception) {
            $this->assertSame('Classification rejected', $exception->getMessage());
        }
        $this->assertFalse($receipt->consumption()->exists());
        $this->assertDatabaseMissing('proposition_receipt_links', ['receipt_id' => $receipt->id]);
        $this->assertTrue(app(HorizonReceiptService::class)->available($phase)->contains('id', $receipt->id));
    }

    public function test_receipt_from_another_horizon_is_not_available_or_consumable(): void
    {
        [$source, $receipt] = $this->observation();
        $boundary = $this->commitFrom($source, boundary: true);
        $phase = $this->phase($boundary, PhaseKind::Distinguish);
        $target = $this->node($phase, $boundary);
        $this->assertTrue(app(HorizonReceiptService::class)->available($phase)->isEmpty());
        $this->expectException(DomainException::class);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($phase, $boundary, $target, $receipt->id));
    }
}
