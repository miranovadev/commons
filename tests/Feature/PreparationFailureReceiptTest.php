<?php

namespace Tests\Feature;

use App\Models\EvidenceExecution;
use App\Models\Receipt;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\ParticipantReceiptProjection;
use App\Services\PreparationService;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\PreparationFixture;
use Tests\Fixtures\TextPdf;
use Tests\Support\RefreshEvidenceDatabase;
use Tests\TestCase;

class PreparationFailureReceiptTest extends TestCase
{
    use RefreshEvidenceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('artifacts');
    }

    public static function adapters(): array
    {
        return [['human-text'], ['inline-text']];
    }

    #[DataProvider('adapters')]
    public function test_storage_failure_is_an_idempotent_ordinary_observation_without_progression(string $adapter): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $input = $fixture->input();
        $input['artifacts'][0]['parameters'] = ['offset' => 2, 'max_chars' => 20000];
        $execution = $service->start($f->viewer, $f->phase, $input);
        $before = $this->structure();
        Storage::disk('artifacts')->delete($fixture->source->metadata['artifacts'][0]['path']);
        try {
            $service->delivery($f->viewer, $execution, $adapter);
            $this->fail('Missing storage was presented.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Preparation reading failed', $e->getMessage());
        }
        $execution->refresh();
        $receipt = $execution->receipt;
        $this->assertSame('failed', $execution->status);
        $this->assertSame('failed', $execution->outcome);
        $this->assertNull($execution->completed_at);
        $this->assertNotNull($execution->failed_at);
        $this->assertNull($execution->presentation);
        $this->assertSame('preparation', $receipt->type);
        $this->assertTrue($receipt->isEvidence());
        $this->assertNull($receipt->signature);
        $this->assertNull($receipt->consumption);
        $this->assertDatabaseMissing('proposition_receipt_links', ['receipt_id' => $receipt->id]);
        $metadata = $receipt->metadata;
        $this->assertSame($execution->id, $metadata['execution_id']);
        $this->assertSame($f->gate->id, $metadata['originating_gate_id']);
        $this->assertSame($f->viewer->id, $metadata['participant_id']);
        $this->assertSame($input['purpose'], $metadata['purpose']);
        $this->assertEquals($input['artifacts'], $metadata['selections']);
        $this->assertSame([], $metadata['deliveries']);
        $this->assertFalse($metadata['content_verified']);
        $this->assertSame(['type' => 'failed'], $metadata['outcome']);
        $this->assertSame('storage_failure', $metadata['failure']['classification']);
        $this->assertSame('read', $metadata['failure']['stage']);
        $this->assertSame($execution->failure_reason, $metadata['failure_reason']);
        $this->assertLessThanOrEqual(1000, mb_strlen($metadata['failure_reason']));
        $this->assertSame($f->phase->cycle->context_horizon_id, $receipt->context_horizon_id);
        $this->assertSame([$receipt->id], app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->modelKeys());
        $this->assertTrue(app(PropositionStateService::class)->receipts($f->gate)->contains('id', $receipt->id));
        $actions = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']['$defs']['actions'];
        $this->assertArrayHasKey('proposition.variant', $actions);
        $this->assertArrayNotHasKey('preparation.start', $actions);
        $this->assertArrayNotHasKey('instrument.execute', $actions);
        $service->fail($f->viewer, $execution, 'retry', '1', 'read', 'Must not overwrite the first observation.');
        $this->assertSame(1, Receipt::where('evidence_execution_id', $execution->id)->count());
        $this->assertSame($metadata, $receipt->fresh()->metadata);
        $this->assertSame(1, EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->where('type', 'prepare')->count());
        $this->assertSame(0, EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->where('type', 'instrument')->count());
        $this->assertSame($before, $this->structure());
    }

    public function test_failure_loads_only_diagnostics_and_can_be_consumed_by_lawful_reclassification(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->fail($f->viewer, $execution, 'trusted-reader', '1', 'read', "Extraction\0 failed.\nNo text delivered.");
        $receipt = $execution->fresh()->receipt;
        $evidence = app(ReceiptEvidenceService::class);
        $this->assertNull($evidence->observation($receipt));
        $this->assertFalse($evidence->failure($receipt)['content_verified']);
        $this->assertStringNotContainsString("\0", $receipt->metadata['failure_reason']);
        $contexts = app(ReceiptContextService::class);
        $contexts->clear($f->viewer, $f->phase, $receipt->id);
        $option = $contexts->forPhase($f->phase)->firstWhere('id', $receipt->id);
        $this->assertSame('available', $option['status']);
        $this->assertSame('failed', $option['preparation_history']['outcome']['type']);
        $contexts->read($f->viewer, $f->phase, $receipt->id);
        $registers = ['gate' => ['propositions' => [['id' => $f->gate->id]]]];
        $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers, [
            'participant' => ['id' => $f->viewer->id], 'plan' => ['phase' => ['id' => $f->phase->id, 'kind' => 'disperse'], 'gate_id' => $f->gate->id],
        ]);
        $loaded = collect($catalogue)->firstWhere('id', $receipt->id)['loaded_inline_content'];
        $this->assertSame('failed', $loaded['outcome']['type']);
        $this->assertSame([], $loaded['deliveries']);
        $this->assertStringContainsString('Operational failure only', $loaded['interpretation']);
        $this->assertStringNotContainsString('preserved artifact body', json_encode($loaded));
        $other = new PreparationFixture;
        $this->assertFalse(app(HorizonReceiptService::class)->ordinaryAvailable($other->context->phase)->contains('id', $receipt->id));
        $classification = $fixture->consume($receipt);
        $this->assertSame($classification->id, $receipt->fresh()->consumption->proposition_id);
        $this->assertSame('failed', $execution->fresh()->status);
        $this->assertFalse(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->contains('id', $receipt->id));
    }

    public function test_expiry_after_acknowledged_presentation_records_the_genuine_attempt(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $delivery = $service->delivery($f->viewer, $execution, 'human-text');
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $presented = $execution->fresh()->presentation;
        $this->travel(31)->minutes();
        $service->recover($f->viewer, $f->phase);
        $execution->refresh();
        $this->assertSame('failed', $execution->status);
        $this->assertSame('failed', $execution->outcome);
        $this->assertSame('expired', $execution->receipt->metadata['failure']['stage']);
        $this->assertSame($presented, $execution->receipt->metadata['deliveries']);
        $this->assertFalse($execution->receipt->metadata['content_verified']);
        $this->assertNull($f->phase->fresh()->completed_at);
        $service->recover($f->viewer, $f->phase);
        $this->assertSame(1, Receipt::where('evidence_execution_id', $execution->id)->count());
    }

    public function test_failure_and_receipt_creation_roll_back_together(): void
    {
        $fixture = new PreparationFixture;
        $service = app(PreparationService::class);
        $f = $fixture->context;
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        Receipt::creating(function (Receipt $receipt): void {
            if ($receipt->type === 'preparation') {
                throw new RuntimeException('Injected failure Receipt persistence error.');
            }
        });
        try {
            $service->fail($f->viewer, $execution, 'reader', '1', 'read', 'Technical failure.');
            $this->fail('Receipt persistence error was bypassed.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected failure Receipt persistence error.', $e->getMessage());
        }
        $this->assertSame('pending', $execution->fresh()->status);
        $this->assertNull($execution->fresh()->outcome);
        $this->assertNull($execution->fresh()->failed_at);
        $this->assertNull($execution->fresh()->receipt);
    }

    public function test_capability_loss_after_valid_start_records_an_unsupported_failure(): void
    {
        $fixture = new PreparationFixture;
        $source = $fixture->receipt(TextPdf::make(['Readable source']));
        $input = $fixture->input([$source]);
        $input['artifacts'][0]['read_type'] = 'text';
        $service = app(PreparationService::class);
        $f = $fixture->context;
        $execution = $service->start($f->viewer, $f->phase, $input);
        config(['preparation.pdf.binary' => '/nonexistent/reader']);
        try {
            $service->delivery($f->viewer, $execution, 'inline-text');
            $this->fail('Missing reader succeeded.');
        } catch (DomainException) {
            $this->assertSame('missing_capability', $execution->fresh()->receipt->metadata['failure']['classification']);
            $this->assertSame('unsupported', $execution->fresh()->receipt->metadata['failure']['stage']);
        }
    }

    public function test_foreign_failure_report_and_stale_reading_do_not_mint_receipts(): void
    {
        $fixture = new PreparationFixture;
        $other = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        try {
            $service->fail($other->context->viewer, $execution, 'client', '1', 'read', 'Invented failure');
            $this->fail('Foreign report accepted.');
        } catch (DomainException) {
            $this->assertNull($execution->fresh()->receipt);
        }
        $f->viewer->forceFill(['current_proposition_id' => $f->seed->id])->save();
        try {
            $service->delivery($f->viewer, $execution, 'human-text');
            $this->fail('Stale reading accepted.');
        } catch (ConflictHttpException) {
            $this->assertSame('pending', $execution->fresh()->status);
            $this->assertNull($execution->fresh()->receipt);
        }
    }

    private function structure(): array
    {
        return collect(['propositions', 'supersessions', 'phases', 'cycles', 'horizon_exploration_states',
            'chirality_observations', 'closure_births', 'chirality_population_observations', 'phase_profile_observations',
            'proposition_attestation_cursors'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
    }
}
