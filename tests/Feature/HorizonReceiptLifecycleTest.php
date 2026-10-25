<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Receipt;
use App\Services\EvidenceExecutionService;
use App\Services\HorizonReceiptService;
use App\Services\Participation\ParticipationContext;
use App\Services\ReceiptContextService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExhaustionFixture;
use Tests\Fixtures\ExplorationHistory;
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
        $service = app(EvidenceExecutionService::class);
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
        $this->assertSame([$receipt->id], app(HorizonReceiptService::class)->ordinaryAvailable($other)->modelKeys());
        $this->assertSame(1, Receipt::where('evidence_execution_id', $execution->id)->count());
        $this->expectException(DomainException::class);
        app(EvidenceExecutionService::class)->complete($execution, $source);
    }

    public function test_receipt_evidence_remains_free_and_shared_when_its_producer_is_spent(): void
    {
        $producerPhase = $this->phase($this->origin, PhaseKind::Distinguish);
        // A spent producer and its unconsumed Receipt remain contextual evidence.
        $producer = $this->node($producerPhase, $this->origin);
        $service = app(EvidenceExecutionService::class);
        $receipt = $service->complete($service->start($producerPhase, $producer, 'citation', $this->origin->id), $producer);
        ExplorationHistory::record($producer);
        $viewer = $this->phase($this->origin, PhaseKind::Distinguish);
        $context = new ParticipationContext($viewer);
        $this->assertTrue($context->propositions->has($producer->id));
        $before = DB::table('substrate_ledger_entries')->count();
        $item = app(ReceiptContextService::class)->forPhase($viewer, $context)->firstWhere('id', $receipt->id);
        $this->assertSame('available', $item['availability']);
        $this->assertSame($this->origin->statement, $item['evidence']['statement']);
        $this->assertSame($before, DB::table('substrate_ledger_entries')->count());
        $this->assertDatabaseCount('participant_receipt_contexts', 1);
        $this->assertNull($receipt->fresh()->consumption);
    }

    public function test_one_use_attachment_preserves_receipt_and_is_visible_to_every_participant(): void
    {
        [$source, $receipt] = $this->observation();
        $phase = $this->phase($source, PhaseKind::Constrain);
        $target = $this->node($phase, $source);
        $service = app(HorizonReceiptService::class);
        $original = $receipt->fresh()->getAttributes();
        DB::transaction(fn () => $service->consume($phase, $source, $target, $receipt->id));
        $this->assertEquals($original, $receipt->fresh()->getAttributes());
        $this->assertTrue($service->ordinaryAvailable($source->phase)->isEmpty());
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
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($phase)->contains('id', $receipt->id));
    }

    public function test_receipt_from_another_horizon_is_not_available_or_consumable(): void
    {
        [$source, $receipt] = $this->observation();
        $boundary = $this->commitFrom($source, boundary: true);
        $phase = $this->phase($boundary, PhaseKind::Distinguish);
        $target = $this->node($phase, $boundary);
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($phase)->isEmpty());
        $this->expectException(DomainException::class);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($phase, $boundary, $target, $receipt->id));
    }
}
