<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Jobs\WakeParticipant;
use App\Models\Attestation;
use App\Models\Connection;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Phase;
use App\Models\Receipt;
use App\Services\AcquisitionAttemptService;
use App\Services\ContributionDispatcher;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\EvidenceExecutionService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PhaseGenerationService;
use App\Services\PotentialProjectionService;
use App\Services\PreparationService;
use App\Services\PropositionMeasurementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\Fixtures\PreparationFixture;
use Tests\Support\RefreshEvidenceDatabase;
use Tests\TestCase;

class DisperseInstrumentFallbackTest extends TestCase
{
    use RefreshEvidenceDatabase;

    private function fixture(): ParticipantContextFixture
    {
        return DB::transaction(function () {
            $f = new ParticipantContextFixture(1, 0, false);
            $f->gate = $f->node($f->seed, 'canon', position: PhaseKind::Constrain);
            $f->phase->forceFill(['kind' => PhaseKind::Disperse, 'gate_proposition_id' => $f->gate->id,
                'write_register' => 'activity'])->save();
            $f->viewer->forceFill(['current_proposition_id' => $f->gate->id])->save();

            app(PropositionMeasurementService::class)->observeChirality($f->gate);

            return $f;
        });
    }

    public function test_receipt_and_artifact_absence_with_unused_allowance_offers_fallback(): void
    {
        $f = $this->fixture();
        $this->assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase));
        $this->assertSame([], app(PreparationService::class)->options($f->phase));
        $this->assertFalse(EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->where('type', 'instrument')->exists());
        // Independent structural eligibility is satisfied; evidence is the missing dimension.
        $this->assertTrue(app(PhaseGenerationService::class)->available($f->phase, 'proposition.variant', prepared: true));
        $actions = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']['$defs']['actions'];
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $this->assertArrayNotHasKey('preparation.start', $actions);
        $this->assertArrayHasKey('instrument.execute', $actions);
    }

    private function input(ParticipantContextFixture $f): array
    {
        return ['action' => 'instrument.execute', 'proposition_id' => $f->gate->id,
            'instrument' => ['kind' => 'citation', 'reference' => $f->seed->id]];
    }

    private function actions(ParticipantContextFixture $f): array
    {
        return app(InputPlanService::class)->forPhase($f->viewer, $f->phase->fresh())['schema']['$defs']['actions'];
    }

    private function structuralSnapshot(): array
    {
        $snapshot = [];
        foreach (['propositions', 'supersessions', 'phases', 'cycles', 'attestations', 'exploration_events',
            'phase_profile_observations', 'chirality_observations', 'chirality_population_observations', 'closure_births'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->sortBy(fn ($row) => json_encode($row))->values()->all();
        }
        $snapshot['measurements'] = DB::table('proposition_measurements')->get()->map(function ($row) {
            $values = (array) $row;
            unset($values['calculated_at'], $values['updated_at']);

            return $values;
        })->sortBy(fn ($row) => json_encode($row))->values()->all();

        return $snapshot;
    }

    public function test_fallback_refresh_is_phase_neutral_shared_and_uses_the_existing_allowance(): void
    {
        $f = $this->fixture();
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $service = app(ParticipationService::class);
        $before = $service->stateFor($f->viewer);
        $snapshot = $this->structuralSnapshot();
        $human = app(InputPlanService::class)->forPhase($f->viewer, $f->phase, workspace: true);
        $this->assertSame($before['plan']['schema'], $human['schema']);
        $this->assertSame($human['schema'], app(ParticipantPromptFactory::class)->make(new Connection, $before)['schema']);
        $this->assertSame([$f->gate->id], $human['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $after = $service->contribute($f->viewer, $f->phase->id, $this->input($f));
        $this->assertSame($snapshot, $this->structuralSnapshot());
        $this->assertSame($before['plan']['phase'], $after['plan']['phase']);
        $this->assertSame(json_encode($before['cycle']), json_encode($after['cycle']));
        $this->assertSame($f->gate->id, $after['plan']['gate_id']);
        $execution = EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->sole();
        $receipt = $execution->receipt;
        $this->assertSame('instrument', $execution->type);
        $this->assertSame('completed', $execution->status);
        $this->assertSame($f->gate->id, $receipt->receiptable_id);
        $this->assertTrue($receipt->isEvidence());
        $this->assertNull($receipt->consumption);
        $this->assertContains($receipt->id, array_column($after['plan']['receipts'], 'id'));
        $this->assertDatabaseHas('participant_receipt_contexts', ['receipt_id' => $receipt->id, 'participant_id' => $f->viewer->id, 'cleared_at' => null]);
        $this->assertArrayHasKey('proposition.variant', $after['plan']['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('instrument.execute', $after['plan']['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('preparation.start', $after['plan']['schema']['$defs']['actions']);
    }

    public static function spentPhases(): array
    {
        return [[PhaseKind::Persist], [PhaseKind::Disperse]];
    }

    #[DataProvider('spentPhases')]
    public function test_consumed_observation_reserves_only_its_exact_phase_even_after_failed_verification(PhaseKind $kind): void
    {
        $f = $this->fixture();
        $phase = $f->phase;
        if ($kind === PhaseKind::Persist) {
            $gate = DB::transaction(fn () => $f->node($f->seed, 'activity', position: PhaseKind::Distinguish));
            $phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id, 'write_register' => 'frontier'])->save();
            $phase = $phase->fresh();
            $f->viewer->forceFill(['current_proposition_id' => $gate->id])->save();
        }
        Http::fake(['*' => Http::response('', 404)]);
        // Failed verification is still a completed ordinary observation under the existing contract.
        $this->mock(HyperlinkVerifier::class,
            fn ($mock) => $mock->shouldReceive('verify')->once()->andThrow(new \DomainException('Missing resource.')));
        $receipt = app(OperationalInstrumentService::class)->execute($phase, $phase->gate,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/missing']);
        $this->assertSame('completed', $receipt->evidenceExecution->status);
        if ($kind === PhaseKind::Persist) {
            // Isolated service fixture: position a separate Phase after the recorded Persist observation.
            $phase->forceFill(['completed_at' => now()])->save();
            $next = $phase->replicate();
            $next->forceFill(['kind' => PhaseKind::Disperse, 'gate_proposition_id' => $f->gate->id, 'write_register' => 'activity', 'completed_at' => null])->save();
            $f->phase = $next->fresh();
            $f->viewer->forceFill(['current_proposition_id' => $f->gate->id])->save();
        }
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->contains('id', $receipt->id));
        $this->consume($f, $receipt);
        $this->assertEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase));
        $actions = $this->actions($f);
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        if ($kind === PhaseKind::Persist) {
            $this->assertArrayHasKey('instrument.execute', $actions);
            $this->assertSame('required', app(AcquisitionAttemptService::class)->state($f->phase)['status']);
            $this->assertNull(app(AcquisitionAttemptService::class)->execution($f->phase));
        } else {
            $this->assertArrayNotHasKey('instrument.execute', $actions);
            $this->assertArrayHasKey('acquisition.defer', $actions);
            $this->expectException(ConflictHttpException::class);
            app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input($f)['instrument']);
        }
    }

    public function test_readable_artifact_offers_choice_and_unattempted_expiry_keeps_acquisition_outstanding(): void
    {
        Storage::fake('artifacts');
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $this->assertArrayHasKey('preparation.start', $this->actions($f));
        $this->assertArrayHasKey('instrument.execute', $this->actions($f));
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $service->fail($f->viewer, $execution, 'test-reader', '1', 'expired', 'Unattempted Preparation expired.');
        $this->assertNull($execution->fresh()->receipt);
        $this->assertFalse($service->eligible($f->phase));
        $this->assertSame(['acquisition.defer'], array_keys($this->actions($f)));
        app(AcquisitionAttemptService::class)->resolve($f->viewer, $execution,
            ['action' => 'acquisition.defer', 'execution_id' => $execution->id, 'reason' => 'End the abandoned attempt']);
        $this->assertNotNull($f->phase->cycle->fresh()->deferred_at);

    }

    private function historicalReceipt(ParticipantContextFixture $f, ?string $body = null): Receipt
    {
        $reference = 'https://example.test/'.str()->ulid();
        $artifact = $body === null ? null : app(ArtifactStore::class)->preserve($body, 'application/octet-stream');
        $service = app(EvidenceExecutionService::class);
        $execution = $service->start($f->gate->phase, $f->gate, 'hyperlink', $reference,
            ['kind' => 'hyperlink', 'reference' => $reference]);

        return $service->complete($execution, $f->gate, verification: new VerifiedHyperlink($reference,
            hash('sha256', $body ?? $reference), CarbonImmutable::now(), artifacts: $artifact === null ? [] : [$artifact]));
    }

    private function consume(ParticipantContextFixture $f, Receipt $receipt): void
    {
        DB::transaction(function () use ($f, $receipt): void {
            $source = $f->node($f->seed, 'frontier', position: PhaseKind::Persist);
            $target = $f->node($source, 'canon', 'reclassify', position: PhaseKind::Constrain);
            app(HorizonReceiptService::class)->consume($target->phase, $source, $target, $receipt->id);
        });
    }

    public function test_consumed_unsupported_artifact_allows_fallback_without_storage_io_under_locks(): void
    {
        Storage::fake('artifacts');
        $f = $this->fixture();
        $receipt = $this->historicalReceipt($f, "\x89PNG\x00\x00");
        $this->consume($f, $receipt);
        $this->assertSame([], app(PreparationService::class)->options($f->phase));
        $this->assertArrayHasKey('instrument.execute', $this->actions($f));
        // Discovery already ran outside the transaction; locked checks use only capabilities.
        $this->mock(ArtifactStore::class, fn ($mock) => $mock->shouldNotReceive('read'));
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $this->input($f));
        $this->assertSame(1, EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->count());
    }

    public static function newlyAvailable(): array
    {
        return ['shared receipt' => [false, false], 'consumed readable artifact' => [true, false], 'probed readable artifact' => [true, true]];
    }

    #[DataProvider('newlyAvailable')]
    public function test_locked_dispatch_preserves_personal_acquisition_when_shared_evidence_arrives(bool $consume, bool $probe): void
    {
        Storage::fake('artifacts');
        $f = $this->fixture();
        $dispatcher = app(ContributionDispatcher::class);
        $input = $this->input($f);
        $observed = $dispatcher->observeInstrument($f->viewer, $f->phase, $input);
        $receipt = $this->historicalReceipt($f, $consume ? 'New readable evidence' : null);
        if ($consume) {
            $this->consume($f, $receipt);
        }
        if ($probe) {
            $this->assertTrue(app(PreparationService::class)->hasReadableArtifacts($f->phase));
        }
        $this->mock(ArtifactStore::class, fn ($mock) => $mock->shouldNotReceive('read'));
        $before = Receipt::count();
        $dispatcher->dispatch($f->viewer, $f->phase, $input, $observed);
        $this->assertSame($before + 1, Receipt::count());
        $this->assertSame(1, EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->count());
    }

    public static function sharedAllowanceStates(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('sharedAllowanceStates')]
    public function test_another_participant_receipt_restores_generation_without_resetting_allowance(bool $used): void
    {
        $f = $this->fixture();
        if ($used) {
            app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $this->input($f));
            $this->consume($f, EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->sole()->receipt);
            $this->assertArrayHasKey('acquisition.defer', $this->actions($f));
        }
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'observer', 'current_proposition_id' => $f->gate->id]);
        $other->save();
        $cycle = $f->phase->cycle->replicate();
        $cycle->forceFill(['participant_id' => $other->id])->save();
        $phase = $f->phase->replicate();
        $phase->forceFill(['cycle_id' => $cycle->id])->save();
        $input = $this->input($f);
        $input['instrument']['reference'] = $f->gate->id;
        app(ContributionDispatcher::class)->dispatch($other, $phase->fresh(), $input);
        $this->assertSame($used ? 1 : 0, EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->count());
        $this->assertSame($used, isset($this->actions($f)['proposition.variant']));
        $this->assertSame(! $used, isset($this->actions($f)['instrument.execute']));
        $this->assertArrayNotHasKey('preparation.start', $this->actions($f));
    }

    public function test_traverse_and_attestation_keep_independent_eligibility(): void
    {
        $f = $this->fixture();
        $write = DB::transaction(fn () => $f->node($f->gate, 'activity', position: PhaseKind::Disperse));
        $vote = (new Attestation)->forceFill(['phase_id' => $write->phase_id, 'proposition_id' => $write->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->assertArrayHasKey('proposition.traverse', $this->actions($f));
        $this->assertArrayNotHasKey('instrument.execute', $this->actions($f));
        $this->assertArrayNotHasKey('attestation.create', $this->actions($f));
        $this->historicalReceipt($f);
        $this->assertArrayHasKey('proposition.traverse', $this->actions($f));
        $this->assertArrayHasKey('attestation.create', $this->actions($f));
        $this->assertArrayNotHasKey('proposition.variant', $this->actions($f), 'A viable continuation still prevents redundant generation.');
        $this->assertArrayNotHasKey('participant.rest', $this->actions($f));
    }

    public function test_autonomous_wake_reinfers_at_same_disperse_gate_then_generates(): void
    {
        $f = $this->fixture();
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $connection = (new Connection)->forceFill(['name' => 'Fallback transport', 'provider' => 'openai',
            'configuration' => ['model' => 'gpt-test'], 'credentials' => ['api_key' => 'test-secret']]);
        $connection->save();
        $link = (new ParticipantConnection)->forceFill(['participant_id' => $f->viewer->id, 'connection_id' => $connection->id,
            'is_enabled' => true, 'configuration' => ['trigger' => ['mode' => 'manual'], 'wake' => ['termination' => 'phase']]]);
        $link->save();
        $requests = 0;
        Http::fake(function ($request) use (&$requests, $f) {
            $requests++;
            $schema = json_encode($request->data()['text']['format']['schema']);
            $this->assertNull($f->phase->fresh()->completed_at);
            $this->assertSame($f->gate->id, $f->viewer->fresh()->current_proposition_id);
            if ($requests === 1) {
                $this->assertStringContainsString('instrument.execute', $schema);
                $this->assertStringNotContainsString('preparation.start', $schema);
                $input = $this->input($f);
            } else {
                $this->assertSame(2, $requests);
                $this->assertStringNotContainsString('instrument.execute', $schema);
                $this->assertStringContainsString('proposition.variant', $schema);
                $input = ['action' => 'proposition.variant', 'statement' => 'Develop the newly acquired shared observation.'];
            }

            return Http::response(['id' => 'fallback-response',
                'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['contribution' => $input])]]]]]);
        });
        app()->call([new WakeParticipant($link->id), 'handle']);
        $this->assertSame(2, $requests);
        $this->assertNotNull($f->phase->fresh()->completed_at);
        $this->assertSame(PhaseKind::Mediate, Phase::where('cycle_id', $f->phase->cycle_id)->whereNull('completed_at')->sole()->kind);
        $this->assertSame(1, EvidenceExecution::where('cycle_id', $f->phase->cycle_id)->count());
    }

    public function test_readable_artifact_outside_causal_catalogue_does_not_suppress_fallback(): void
    {
        Storage::fake('artifacts');
        $f = $this->fixture();
        $foreign = $this->fixture();
        $this->historicalReceipt($foreign, 'Readable but outside this substrate.');
        $service = app(PreparationService::class);
        $this->assertTrue($service->hasReadableArtifacts($foreign->phase));
        $this->assertFalse($service->hasReadableArtifacts($f->phase));
        $this->assertArrayHasKey('instrument.execute', $this->actions($f));
    }
}
