<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\PropositionStateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class ReceiptGatedReclassificationTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public static function boundaries(): array
    {
        return ['Constrain' => [PhaseKind::Constrain], 'Bias' => [PhaseKind::Bias]];
    }

    private function boundary(PhaseKind $kind): array
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::from($kind->value - 1)), $this->origin);
        $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        // Evidence produced by a different Participant at a different local position
        // is deliberately usable, provided the creating Horizon is the same.
        $producer = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $instruments = app(InstrumentExecutionService::class);
        $receipt = $instruments->complete($instruments->start($producer->phase, $producer, 'citation', $this->origin->id), $producer);
        $phase = $this->phase($source, $kind);

        return [$source, $receipt, $phase];
    }

    #[DataProvider('boundaries')]
    public function test_reclassification_consumes_selected_shared_receipt_and_preserves_position(PhaseKind $kind): void
    {
        [$source, $receipt, $phase] = $this->boundary($kind);
        $input = ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id];
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        app(ContributionService::class)->validateSchema($plan['schema'], $input);
        $result = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase, $input);
        $this->assertSame($source->statement, $result->statement);
        $this->assertSame($phase->write_register, $result->register);
        $this->assertSame($kind, $result->phase->kind);
        $this->assertSame($receipt->id, app(PropositionStateService::class)->receipts($result)->sole()->id);
        $this->assertSame($result->id, $receipt->fresh()->consumption->proposition_id);
        $this->assertSame(0, $result->attestations()->count());
        $this->assertTrue(app(HorizonReceiptService::class)->available($phase)->isEmpty());
        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }

    #[DataProvider('boundaries')]
    public function test_missing_receipt_rejects_without_mutation(PhaseKind $kind): void
    {
        [$source, , $phase] = $this->boundary($kind);
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
                ['action' => 'proposition.reclassify', 'proposition_id' => $source->id]);
            $this->fail('Missing Receipt must reject');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
            $this->assertDatabaseCount('receipt_consumptions', 0);
        }
    }

    #[DataProvider('boundaries')]
    public function test_schema_requires_explicit_receipt_selection(PhaseKind $kind): void
    {
        [$source, , $phase] = $this->boundary($kind);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $this->expectException(InvalidArgumentException::class);
        app(ContributionService::class)->validateSchema($plan['schema'], ['action' => 'proposition.reclassify', 'proposition_id' => $source->id]);
    }

    #[DataProvider('boundaries')]
    public function test_wrong_horizon_receipt_rolls_back_the_entire_classification(PhaseKind $kind): void
    {
        [$source, $receipt, $phase] = $this->boundary($kind);
        $foreign = $this->laterHorizonFixture($phase->cycle->contextHorizon);
        DB::table('receipts')->where('id', $receipt->id)->update(['context_horizon_id' => $foreign->id]);
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
                ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id]);
            $this->fail('Foreign Receipt must reject');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
            $this->assertDatabaseCount('reclassification_results', 0);
            $this->assertDatabaseCount('receipt_consumptions', 0);
        }
    }

    #[DataProvider('boundaries')]
    public function test_reusing_consumed_receipt_on_another_source_is_rejected(PhaseKind $kind): void
    {
        [$source, $receipt, $phase] = $this->boundary($kind);
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $receipt->id]);
        [$other, , $otherPhase] = $this->boundary($kind);
        $this->expectException(DomainException::class);
        app(ContributionDispatcher::class)->dispatch($otherPhase->cycle->participant, $otherPhase,
            ['action' => 'proposition.reclassify', 'proposition_id' => $other->id, 'receipt_id' => $receipt->id]);
    }

    public function test_same_label_from_the_wrong_producing_phase_cannot_be_reclassified(): void
    {
        [$source, $receipt] = $this->boundary(PhaseKind::Bias);
        // Persist and Mediate share a label, but only Mediate is a Bias Gate.
        $wrong = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $phase = $this->phase($wrong, PhaseKind::Bias);
        $this->expectException(DomainException::class);
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.reclassify', 'proposition_id' => $wrong->id, 'receipt_id' => $receipt->id]);
    }
}
