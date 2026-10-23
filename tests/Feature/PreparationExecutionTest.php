<?php

namespace Tests\Feature;

use App\Domain\Evidence\PreparationInput;
use App\Domain\Evidence\PreparationPresentation;
use App\Enums\PhaseKind;
use App\Enums\ReceiptCapability;
use App\Models\Attestation;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\ExplorationEpochService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipantReceiptProjection;
use App\Services\PreparationService;
use App\Services\PropositionOptionService;
use App\Services\ReceiptContextService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\PreparationFixture;
use Tests\TestCase;

class PreparationExecutionTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('artifacts');
    }

    public function test_deferment_is_ordinary_shared_unattached_history_without_structural_progression(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        PreparationFixture::registerReader();
        $service = app(PreparationService::class);
        $before = $this->structuralSnapshot();
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $this->assertSame('prepare', $execution->type);
        $this->assertSame($f->phase->cycle_id, $execution->cycle_id);
        $this->assertSame($f->gate->id, $execution->proposition_id);
        $this->assertNull($execution->kind);
        $this->assertNull($execution->reference);
        $this->assertNull($execution->receipt);
        $this->assertSame($fixture->input(), $execution->input);
        $service->present($f->viewer, $execution, $fixture->presentation());
        $this->assertSame('presented', $execution->fresh()->status);
        $this->assertNull($execution->fresh()->receipt);
        $reason = '  Further comparison is needed; this is a reason, not an instruction.  ';
        $receipt = $service->defer($f->viewer, $execution, $reason);
        $this->assertSame($before, $this->structuralSnapshot());
        $this->assertNull($f->phase->fresh()->completed_at);
        $this->assertSame($f->gate->id, $f->viewer->fresh()->current_proposition_id);
        $this->assertSame('completed', $execution->fresh()->status);
        $this->assertSame('deferred', $execution->fresh()->outcome);
        $this->assertNull($execution->fresh()->resulting_proposition_id);
        $this->assertSame(['type' => 'deferred', 'reason' => $reason], $receipt->metadata['outcome']);
        $this->assertSame($fixture->input()['purpose'], $receipt->metadata['purpose']);
        $this->assertEquals($fixture->input()['artifacts'], $receipt->metadata['selections']);
        $this->assertOrdinary($receipt, $execution);
        $this->assertNotNull(app(ReceiptEvidenceService::class)->observation($receipt));
        $this->assertDatabaseHas('participant_receipt_contexts', ['participant_id' => $f->viewer->id, 'receipt_id' => $receipt->id, 'cleared_at' => null]);
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->contains('id', $receipt->id));
        $other = $this->other($f->phase);
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($other)->contains('id', $receipt->id));
        $registers = ['gate' => ['propositions' => [['id' => $f->gate->id]]], 'read' => ['propositions' => []], 'write' => ['propositions' => []]];
        $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers, ['participant' => $other->cycle->participant->only(['id']),
            'plan' => ['phase' => ['id' => $other->id, 'kind' => 'disperse'], 'gate_id' => $f->gate->id]]);
        $this->assertContains($receipt->id, array_column($catalogue, 'id'));
        $this->assertStringNotContainsString('preserved artifact body', json_encode($catalogue));
        $this->assertSame([$f->gate->id], array_column($registers['gate']['propositions'], 'id'));
        $this->expectException(DomainException::class);
        $receipt->forceFill(['metadata' => []])->save();
    }

    public function test_contributed_outcome_is_atomic_and_advances_only_the_normal_variant_epoch_and_phase(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        [$service, $execution] = $this->presented($fixture);
        $epoch = app(ExplorationEpochService::class)->forPhase($f->phase);
        $count = Proposition::count();
        $phaseCount = Phase::count();
        $receipt = $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'A conclusion from the prepared material.']);
        $result = $execution->fresh()->resultingProposition;
        $this->assertSame($count + 1, Proposition::count());
        $this->assertSame($phaseCount + 1, Phase::count());
        $this->assertSame($epoch + 1, app(ExplorationEpochService::class)->forPhase($f->phase));
        $this->assertSame($result->id, $f->viewer->fresh()->current_proposition_id);
        $this->assertDatabaseHas('phases', ['cycle_id' => $f->phase->cycle_id, 'kind' => PhaseKind::Mediate->value, 'gate_proposition_id' => $result->id, 'completed_at' => null]);
        $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $f->gate->id, 'target_proposition_id' => $result->id, 'type' => 'variant']);
        $this->assertSame(['type' => 'contributed', 'proposition_id' => $result->id], $receipt->metadata['outcome']);
        $this->assertOrdinary($receipt, $execution);
        $original = $receipt->fresh()->metadata;
        $target = $this->reclassify($fixture, $receipt);
        $this->assertNotSame($result->id, $target->id);
        $this->assertSame($original, $receipt->fresh()->metadata);
        $this->assertSame($result->id, $execution->fresh()->resulting_proposition_id);
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id, 'proposition_id' => $target->id]);
    }

    public function test_deferred_receipt_can_be_consumed_by_another_participant_without_rewriting_its_outcome(): void
    {
        $fixture = new PreparationFixture;
        [$service, $execution] = $this->presented($fixture);
        $f = $fixture->context;
        $receipt = $service->defer($f->viewer, $execution, 'Await a better comparison.');
        $original = $receipt->fresh()->metadata;
        $target = $this->reclassify($fixture, $receipt);
        $this->assertNotSame($f->viewer->id, $target->phase->cycle->participant_id);
        $this->assertSame($original, $receipt->fresh()->metadata);
        $this->assertFalse(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->contains('id', $receipt->id));
        $this->assertSame('deferred', $execution->fresh()->outcome);
        $this->assertNull($execution->fresh()->resulting_proposition_id);
    }

    public function test_multiple_receipts_preserve_all_associations_but_same_bytes_are_delivered_once(): void
    {
        $fixture = new PreparationFixture;
        $same = $fixture->receipt('preserved artifact body');
        $different = $fixture->receipt('another preserved artifact');
        $input = $fixture->input([$fixture->source, $same, $different]);
        $f = $fixture->context;
        PreparationFixture::registerReader();
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        $service->present($f->viewer, $execution, $fixture->presentation($input));
        $receipt = $service->defer($f->viewer, $execution, 'Compared all selected observations.');
        $this->assertCount(3, $receipt->metadata['selections']);
        $this->assertCount(2, $receipt->metadata['deliveries']);
        $this->assertSame('application/octet-stream', $different->metadata['artifacts'][0]['media_type']);
    }

    public static function invalidInputs(): array
    {
        return array_map(fn ($v) => [$v], ['purpose-missing', 'purpose-empty', 'purpose-long', 'empty', 'duplicate', 'excessive', 'hash', 'unclaimed', 'unknown-receipt', 'extra']);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_selections_and_purpose_never_reserve_an_allowance(string $case): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $input = $fixture->input();
        switch ($case) {
            case 'purpose-missing': unset($input['purpose']);
                break;
            case 'purpose-empty': $input['purpose'] = '  ';
                break;
            case 'purpose-long': $input['purpose'] = str_repeat('界', 241);
                break;
            case 'empty': $input['artifacts'] = [];
                break;
            case 'duplicate': $input['artifacts'][] = $input['artifacts'][0];
                break;
            case 'excessive': $input['artifacts'] = array_fill(0, PreparationInput::MAX_SELECTIONS + 1, $input['artifacts'][0]);
                break;
            case 'hash': $input['artifacts'][0]['artifact_id'] = 'sha256:bad';
                break;
            case 'unclaimed': $input['artifacts'][0]['artifact_id'] = 'sha256:'.str_repeat('0', 64);
                break;
            case 'unknown-receipt': $input['artifacts'][0]['receipt_id'] = (string) str()->ulid();
                break;
            case 'extra': $input['artifacts'][0]['contents'] = 'untrusted';
                break;
        }
        try {
            app(PreparationService::class)->start($f->viewer, $f->phase, $input);
            $this->fail('Invalid request accepted.');
        } catch (DomainException) {
            $this->assertSame(0, EvidenceExecution::where('type', 'prepare')->count());
        }
    }

    public function test_foreign_receipts_are_rejected_and_consumed_artifacts_remain_accessible_independently_of_loading(): void
    {
        $fixture = new PreparationFixture;
        $foreign = new PreparationFixture;
        $f = $fixture->context;
        foreach ([$foreign->input(), $fixture->input([$foreign->source])] as $input) {
            try {
                app(PreparationService::class)->start($f->viewer, $f->phase, $input);
                $this->fail('Foreign scope accepted.');
            } catch (DomainException) {
                $this->assertSame(0, EvidenceExecution::where('type', 'prepare')->count());
            }
        }
        DB::table('participant_receipt_contexts')->where('receipt_id', $fixture->source->id)->update(['cleared_at' => now()]);
        $execution = app(PreparationService::class)->start($f->viewer, $f->phase, $fixture->input());
        $this->assertSame('pending', $execution->status);
        $other = $this->other($f->phase);
        $second = app(PreparationService::class)->start($other->cycle->participant, $other, $fixture->input());
        $this->assertSame('pending', $second->status);
        $this->assertTrue(app(HorizonReceiptService::class)->accessibleOrdinary($other)->contains('id', $fixture->source->id));
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($other)->isEmpty());
    }

    public static function invalidPresentations(): array
    {
        return array_map(fn ($v) => [$v], ['unknown-reader', 'wrong-version', 'unsupported', 'omitted', 'duplicate', 'budget', 'negative', 'unselected', 'body', 'false-length', 'wrong-origin', 'raw-hash']);
    }

    #[DataProvider('invalidPresentations')]
    public function test_invalid_delivery_cannot_claim_successful_examination(string $case): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        PreparationFixture::registerReader();
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $deliveries = $fixture->presentation()->deliveries;
        switch ($case) {
            case 'unknown-reader': $deliveries[0]['method'] = 'unregistered';
                break;
            case 'wrong-version': $deliveries[0]['version'] = 'unknown-future-version';
                break;
            case 'unsupported': $deliveries[0]['representation'] = 'unsupported-image';
                break;
            case 'omitted': $deliveries = [];
                break;
            case 'duplicate': $deliveries[] = $deliveries[0];
                break;
            case 'budget': $deliveries[0]['payload_bytes'] = PreparationInput::MAX_PAYLOAD_BYTES + 1;
                break;
            case 'negative': $deliveries[0]['payload_bytes'] = -1;
                break;
            case 'unselected': $deliveries[0]['artifact_id'] = 'sha256:'.str_repeat('0', 64);
                break;
            case 'false-length': $deliveries[0]['boundaries']['length'] = 0;
                break;
            case 'wrong-origin': $deliveries[0]['receipt_ids'] = [(string) str()->ulid()];
                break;
            case 'raw-hash': $deliveries[0]['representation_hash'] = 'sha256:'.str_repeat('0', 64);
                break;
            case 'body': $deliveries[0]['body'] = 'contents';
                break;
        }
        try {
            $service->present($f->viewer, $execution, new PreparationPresentation($deliveries));
            $this->fail('Invalid delivery accepted.');
        } catch (DomainException) {
            $this->assertSame('pending', $execution->fresh()->status);
            $this->assertNull($execution->fresh()->presented_at);
            $this->assertNull($execution->fresh()->receipt);
        }
    }

    public function test_production_exposes_prepare_in_disperse_only_when_consumed_artifacts_remain_accessible(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $actions = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']['$defs']['actions'];
        $this->assertArrayHasKey('preparation.start', $actions);
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $this->assertArrayNotHasKey('proposition.revise', $actions);
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->isEmpty());
    }

    public function test_failure_records_method_and_diagnostic_without_receipt_or_outcome_and_keeps_allowance_reserved(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $before = $this->structuralSnapshot();
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->fail($f->viewer, $execution, 'native-extract', '1', 'read', 'Unsupported encoding; no material delivered.');
        $execution->refresh();
        $this->assertSame('failed', $execution->status);
        $this->assertNull($execution->presentation);
        $this->assertNull($execution->outcome);
        $this->assertNull($execution->receipt);
        $this->assertNotNull($execution->failed_at);
        $this->assertSame('native-extract', $execution->metadata['failure']['method']);
        $this->assertSame('1', $execution->metadata['failure']['version']);
        $this->assertSame('read', $execution->metadata['failure']['stage']);
        $this->assertSame($before, $this->structuralSnapshot());
        $this->expectException(ConflictHttpException::class);
        $service->start($f->viewer, $f->phase, $fixture->input());
    }

    public function test_completion_requires_presentation_and_can_resolve_only_once(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        try {
            $service->defer($f->viewer, $execution, 'Not yet read.');
            $this->fail('Unpresented material completed.');
        } catch (ConflictHttpException) {
            $this->assertNull($execution->fresh()->receipt);
        }
        PreparationFixture::registerReader();
        $service->present($f->viewer, $execution, $fixture->presentation());
        foreach (['', str_repeat('界', 401)] as $invalid) {
            try {
                $service->defer($f->viewer, $execution, $invalid);
                $this->fail('Invalid reason accepted.');
            } catch (DomainException) {
                $this->assertSame('presented', $execution->fresh()->status);
            }
        }
        $service->defer($f->viewer, $execution, str_repeat('界', 400));
        foreach (['defer', 'contribute'] as $operation) {
            try {
                $operation === 'defer' ? $service->defer($f->viewer, $execution, 'Again')
                    : $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'Again']);
                $this->fail('Completed Preparation resolved twice.');
            } catch (ConflictHttpException) {
                $this->assertSame(1, Receipt::where('type', 'preparation')->count());
            }
        }
    }

    public function test_stale_gate_and_foreign_actor_cannot_complete(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        [$service, $execution] = $this->presented($fixture);
        $other = $this->other($f->phase);
        try {
            $service->defer($other->cycle->participant, $execution, 'Not mine.');
            $this->fail('Foreign ownership accepted.');
        } catch (ConflictHttpException) {
            $this->assertSame('presented', $execution->fresh()->status);
        }
        $f->viewer->forceFill(['current_proposition_id' => $f->seed->id])->save();
        $this->expectException(ConflictHttpException::class);
        $service->defer($f->viewer, $execution, 'Stale gate.');
    }

    public function test_receipt_failure_rolls_back_real_contribution_phase_and_epoch(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        [$service, $execution] = $this->presented($fixture);
        $before = $this->structuralSnapshot();
        Receipt::creating(function (Receipt $r): void {
            if ($r->type === 'preparation') {
                throw new RuntimeException('Injected Receipt persistence failure');
            }
        });
        try {
            $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'Must roll back.']);
            $this->fail('Failure injection did not run.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected Receipt persistence failure', $e->getMessage());
        }
        $this->assertSame($before, $this->structuralSnapshot());
        $this->assertSame('presented', $execution->fresh()->status);
        $this->assertNull($execution->fresh()->receipt);
        $this->assertNull($execution->fresh()->resulting_proposition_id);
    }

    public function test_revision_preserves_ownership_refutation_and_does_not_advance_variant_epoch(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $head = DB::transaction(fn () => $f->node($f->gate, 'activity', position: PhaseKind::Disperse));
        $vote = (new Attestation)->forceFill(['phase_id' => $head->phase_id, 'proposition_id' => $head->id, 'direction' => 'refute']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        [$service, $execution] = $this->presented($fixture);
        $epoch = app(ExplorationEpochService::class)->forPhase($f->phase);
        $receipt = $service->contribute($f->viewer, $execution, ['action' => 'proposition.revise',
            'proposition_id' => $head->id, 'statement' => 'Reconsider the refuted account after examination.']);
        $result = $execution->fresh()->resultingProposition;
        $this->assertSame([$head->id], $result->parents()->pluck('propositions.id')->all());
        $this->assertSame($epoch, app(ExplorationEpochService::class)->forPhase($f->phase));
        $this->assertOrdinary($receipt, $execution);
        $this->assertNotNull($f->phase->fresh()->completed_at);
    }

    public function test_allowances_are_independent_between_participants_cycles_and_execution_types(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        [$service, $execution] = $this->presented($fixture);
        $receipt = $service->defer($f->viewer, $execution, 'The observation stands.');
        $this->reclassify($fixture, $receipt);
        $other = $this->other($f->phase);
        $second = $service->start($other->cycle->participant, $other, $fixture->input());
        $this->assertNotSame($execution->cycle_id, $second->cycle_id);
        $this->assertSame(0, EvidenceExecution::where('cycle_id', $execution->cycle_id)->where('type', 'instrument')->where('status', 'completed')->count());
        // Isolate the allowance predicate from Phase ordering: a completed prepare
        // row must not be counted as an Instrument, even in the same Cycle.
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->isEmpty());
        $persistGate = DB::transaction(fn () => $f->node($f->seed, 'activity', position: PhaseKind::Distinguish));
        $probe = $f->phase->replicate();
        $probe->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $persistGate->id, 'write_register' => 'frontier']);
        $this->assertNotEmpty(app(PropositionOptionService::class)->instrumentOptionsFor($probe));
        $f->phase->forceFill(['completed_at' => now()])->save();
        $f->phase->cycle->forceFill(['completed_at' => now()])->save();
        $next = $f->phase->cycle->replicate();
        $next->forceFill(['completed_at' => null, 'sequence' => (int) $f->viewer->cycles()->max('sequence') + 1])->save();
        $phase = $f->phase->replicate();
        $phase->forceFill(['cycle_id' => $next->id, 'completed_at' => null])->save();
        $newSource = $fixture->receipt('new observation in a later Cycle');
        $nextExecution = $service->start($f->viewer, $phase, $fixture->input([$newSource]));
        $this->assertNotSame($execution->cycle_id, $nextExecution->cycle_id);
        $this->assertSame($f->viewer->id, $nextExecution->cycle->participant_id);
    }

    public function test_selection_authority_survives_later_consumption_without_granting_new_access(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        PreparationFixture::registerReader();
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $this->assertNotNull($fixture->source->consumption);
        $this->assertFalse(app(HorizonReceiptService::class)->catalogue($f->phase)->contains('id', $fixture->source->id));
        $service->present($f->viewer, $execution, $fixture->presentation());
        $receipt = $service->defer($f->viewer, $execution, 'The selected material was supplied.');
        $this->assertEquals($fixture->input()['artifacts'], $receipt->metadata['selections']);
    }

    public function test_failed_phase_advance_rolls_back_receipt_outcome_and_contribution(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        [$service, $execution] = $this->presented($fixture);
        $before = $this->structuralSnapshot();
        Phase::creating(function (Phase $phase): void {
            if ($phase->kind === PhaseKind::Mediate) {
                throw new RuntimeException('Injected progression failure');
            }
        });
        try {
            $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'Roll back the entire resolution.']);
            $this->fail('Failure injection did not run.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected progression failure', $e->getMessage());
        }
        $this->assertSame($before, $this->structuralSnapshot());
        $this->assertSame('presented', $execution->fresh()->status);
        $this->assertNull($execution->fresh()->receipt);
        $this->assertNull($execution->fresh()->outcome);
        $this->assertSame($f->gate->id, $f->viewer->fresh()->current_proposition_id);
    }

    public function test_persisted_examples_cover_each_execution_outcome(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        [$service, $execution] = $this->presented($fixture);
        $contributed = $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'A prepared result.']);
        $deferredFixture = new PreparationFixture;
        [$service, $deferredExecution] = $this->presented($deferredFixture);
        $deferred = $service->defer($deferredFixture->context->viewer, $deferredExecution, 'The comparison is inconclusive.');
        $failedFixture = new PreparationFixture;
        $failed = $service->start($failedFixture->context->viewer, $failedFixture->context->phase, $failedFixture->input());
        $service->fail($failedFixture->context->viewer, $failed, 'test-acknowledgement', '1', 'delivery', 'No material reached the participant.');
        $samples = ['instrument' => ['execution' => $fixture->source->evidenceExecution->fresh()->toArray(), 'receipt' => $fixture->source->fresh()->toArray()],
            'contributed' => ['execution' => $execution->fresh()->toArray(), 'receipt' => $contributed->fresh()->toArray()],
            'deferred' => ['execution' => $deferredExecution->fresh()->toArray(), 'receipt' => $deferred->fresh()->toArray()],
            'failed' => ['execution' => $failed->fresh()->toArray(), 'receipt' => null]];
        $this->assertSame('instrument', $samples['instrument']['execution']['type']);
        $this->assertSame('contributed', $samples['contributed']['execution']['outcome']);
        $this->assertSame('deferred', $samples['deferred']['execution']['outcome']);
        $this->assertSame('failed', $samples['failed']['execution']['status']);
        $this->assertNull($samples['failed']['execution']['outcome']);
        if ($path = getenv('COMMONS_PREPARATION_EXAMPLES')) {
            file_put_contents($path, json_encode(['description' => 'Rows from isolated domain tests; delivery acknowledgements use a test-only reader, not a Stage 2D implementation.',
                'records' => $samples], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        }
    }

    public function test_preparation_receipt_alone_enables_shared_persist_generation_independently_of_loading(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        [$service, $execution] = $this->presented($fixture);
        $receipt = $service->defer($f->viewer, $execution, 'Preserve the comparison for later development.');
        $this->assertNotNull($fixture->source->consumption);
        $other = $this->other($f->phase);
        $gate = DB::transaction(fn () => $f->node($f->seed, 'activity', position: PhaseKind::Distinguish));
        $other->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $gate->id, 'write_register' => 'frontier'])->save();
        $actor = $other->cycle->participant;
        $actor->forceFill(['current_proposition_id' => $gate->id])->save();
        $this->assertSame([$receipt->id], app(HorizonReceiptService::class)->ordinaryAvailable($other)->modelKeys());
        $contexts = app(ReceiptContextService::class);
        $contexts->clear($f->viewer, $f->phase, $receipt->id);
        foreach (['unloaded', 'loaded', 'cleared'] as $state) {
            if ($state === 'loaded') {
                $contexts->read($actor, $other, $receipt->id);
            }
            if ($state === 'cleared') {
                $contexts->clear($actor, $other, $receipt->id);
            }
            $actions = app(InputPlanService::class)->forPhase($actor, $other)['schema']['$defs']['actions'];
            $this->assertArrayHasKey('proposition.variant', $actions);
            $this->assertArrayNotHasKey('instrument.execute', $actions);
        }
        $result = app(ContributionDispatcher::class)->dispatch($actor, $other,
            ['action' => 'proposition.variant', 'statement' => 'Develop another Participant’s Preparation observation.']);
        $this->assertSame([$gate->id], $result->parents()->pluck('propositions.id')->all());
        $this->assertOrdinary($receipt, $execution);
        $this->assertSame('deferred', $execution->fresh()->outcome);
    }

    public function test_failed_delivery_after_presentation_retains_only_the_delivery_that_actually_happened(): void
    {
        $fixture = new PreparationFixture;
        [$service, $execution] = $this->presented($fixture);
        $presented = $execution->fresh()->presentation;
        $service->fail($fixture->context->viewer, $execution, 'test-acknowledgement', '1', 'delivery', 'Resolution channel failed after presentation.');
        $this->assertSame($presented, $execution->fresh()->presentation);
        $this->assertNull($execution->fresh()->outcome);
        $this->assertNull($execution->fresh()->receipt);
        $this->assertSame('failed', $execution->fresh()->status);
    }

    private function presented(PreparationFixture $fixture): array
    {
        PreparationFixture::registerReader();
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->present($f->viewer, $execution, $fixture->presentation());

        return [$service, $execution];
    }

    private function assertOrdinary(Receipt $receipt, EvidenceExecution $execution): void
    {
        $this->assertSame('preparation', $receipt->type);
        $this->assertSame($execution->id, $receipt->evidence_execution_id);
        $this->assertSame($execution->proposition_id, $receipt->receiptable_id);
        $this->assertTrue($receipt->isEvidence());
        $this->assertTrue($receipt->observationComplete());
        $this->assertFalse($receipt->supports(ReceiptCapability::Replayable));
        $this->assertNull($receipt->replayInvocation());
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $receipt->id]);
        $this->assertDatabaseMissing('proposition_receipt_links', ['receipt_id' => $receipt->id]);
    }

    private function other(Phase $phase): Phase
    {
        $participant = (new Participant)->forceFill(['substrate_id' => $phase->cycle->participant->substrate_id,
            'callsign' => 'another-'.str()->ulid(), 'current_proposition_id' => $phase->gate_proposition_id]);
        $participant->save();
        $cycle = $phase->cycle->replicate();
        $cycle->participant_id = $participant->id;
        $cycle->save();
        $copy = $phase->replicate();
        $copy->cycle_id = $cycle->id;
        $copy->save();

        return $copy->fresh();
    }

    /** Full ordinary dispatch, including effective Attestation and independent ownership. */
    private function reclassify(PreparationFixture $fixture, Receipt $receipt): Proposition
    {
        return $fixture->consume($receipt);
    }

    private function structuralSnapshot(): array
    {
        return collect(['propositions', 'supersessions', 'phases', 'cycles', 'horizon_exploration_states',
            'chirality_observations', 'closure_births', 'chirality_population_observations', 'phase_profile_observations', 'proposition_attestation_cursors'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->sortBy(fn ($row) => json_encode($row))->values()->toJson()])->all();
    }
}
