<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\ReceiptCapability;
use App\Exceptions\ArtifactStorageException;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Receipt;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\DownloadAcquisition;
use App\Services\Evidence\PublicResourceAddress;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipationService;
use App\Services\PreparationService;
use App\Services\ReceiptContextService;
use App\Services\RelevantInstrumentService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\Fixtures\PreparationFixture;
use Tests\Support\RefreshEvidenceDatabase;
use Tests\TestCase;

class LoadFileInstrumentTest extends TestCase
{
    use RefreshEvidenceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(PublicResourceAddress::class, fn ($m) => $m->shouldReceive('resolve')->andReturn('93.184.216.34'));
        Http::preventStrayRequests();
        Storage::fake('artifacts');
    }

    private function fixture(): ParticipantContextFixture
    {
        return DB::transaction(function () {
            $f = new ParticipantContextFixture(1, 0, false);
            $f->gate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
            $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $f->gate->id, 'write_register' => 'frontier'])->save();
            $f->viewer->forceFill(['current_proposition_id' => $f->gate->id])->save();

            return $f;
        });
    }

    private function input(): array
    {
        return ['kind' => 'load_file', 'method' => 'download', 'reference' => 'https://example.com/data?version=1'];
    }

    public function test_real_action_refreshes_persist_without_loading_or_structural_progression(): void
    {
        $f = $this->fixture();
        $body = 'Download body sentinel — deliberately not cognitive content.';
        Http::fake(function () use ($body) {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response($body, 200, ['Content-Type' => 'text/plain']);
        });
        $plan = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        $this->assertStringContainsString('load_file', json_encode($plan['schema']));
        $this->assertStringNotContainsString('upload', json_encode($plan['schema']));
        $this->publishPotentialFixture($f->viewer);
        $counts = fn () => array_map(fn ($table) => DB::table($table)->count(), ['propositions', 'phases', 'cycles', 'attestations', 'supersessions']);
        $before = $counts();
        $state = app(ParticipationService::class)->contribute($f->viewer, $f->phase->id,
            ['action' => 'instrument.execute', 'proposition_id' => $f->gate->id, 'instrument' => $this->input()]);
        $receipt = Receipt::where('type', 'load_file')->sole();
        $this->assertSame($before, $counts());
        $this->assertSame($f->phase->id, $state['plan']['phase']['id']);
        $this->assertNull($f->phase->fresh()->completed_at);
        $this->assertSame($f->gate->id, $receipt->evidenceExecution->proposition_id);
        $this->assertSame('instrument', $receipt->evidenceExecution->type);
        $this->assertSame('completed', $receipt->evidenceExecution->status);
        $this->assertTrue($receipt->isEvidence());
        $this->assertFalse($receipt->supports(ReceiptCapability::Readable));
        $this->assertTrue($receipt->supports(ReceiptCapability::Replayable));
        $this->assertSame($this->input(), $receipt->replayInvocation());
        $this->assertNull($receipt->consumption);
        $this->assertDatabaseCount('participant_receipt_contexts', 0);
        $this->assertDatabaseCount('receipt_context_events', 0);
        $this->assertDatabaseCount('reinforcements', 0);
        $this->assertArrayHasKey('proposition.variant', $state['plan']['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('instrument.execute', $state['plan']['schema']['$defs']['actions']);
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->contains('id', $receipt->id));
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $item = collect($packet['receipts'])->firstWhere('id', $receipt->id);
        $this->assertFalse($item['loaded']);
        $this->assertSame('download', $item['acquisition']['method']);
        $this->assertSame($receipt->metadata['artifacts'][0]['hash'], $item['acquisition']['artifacts'][0]['hash']);
        $this->assertArrayNotHasKey('loaded_inline_content', $item);
        $this->assertStringNotContainsString($body, json_encode($packet));
        $this->assertStringNotContainsString('artifacts/sha256', json_encode($packet));
        Http::assertSentCount(1);
        try {
            app(ReceiptContextService::class)->read($f->viewer, $f->phase, $receipt->id);
            $this->fail('Non-loadable Receipt was loaded');
        } catch (DomainException) {
            $this->assertDatabaseCount('participant_receipt_contexts', 0);
        }
        try {
            app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input());
            $this->fail('Spent Instrument allowance accepted');
        } catch (ConflictHttpException) {
            Http::assertSentCount(1);
        }
    }

    public function test_shared_receipt_enables_generation_without_loading_and_consumption_preserves_artifact_access(): void
    {
        $f = $this->fixture();
        $observer = DB::transaction(function () use ($f) {
            $participant = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'Downloader', 'current_proposition_id' => $f->gate->id]);
            $participant->save();
            $cycle = $f->phase->cycle->replicate();
            $cycle->participant_id = $participant->id;
            $cycle->save();
            $phase = $f->phase->replicate();
            $phase->cycle_id = $cycle->id;
            $phase->save();

            return $phase;
        });
        Http::fake(['*' => Http::response('Shared acquired text', 200, ['Content-Type' => 'text/plain'])]);
        $receipt = app(OperationalInstrumentService::class)->execute($observer, $f->gate, $this->input());
        $actions = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']['$defs']['actions'];
        $this->assertArrayHasKey('proposition.variant', $actions);
        $this->assertArrayNotHasKey('instrument.execute', $actions);
        $this->assertDatabaseCount('participant_receipt_contexts', 0);
        $original = $receipt->fresh()->getAttributes();
        DB::transaction(fn () => $this->consumeObservationFixture($f, $receipt));
        $this->assertSame($original, $receipt->fresh()->getAttributes());
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->isEmpty());
        $this->assertTrue(app(HorizonReceiptService::class)->accessibleOrdinary($f->phase)->contains('id', $receipt->id));
        $this->assertSame('Shared acquired text', app(ArtifactStore::class)->read($receipt->metadata['artifacts'][0]));
        $actions = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']['$defs']['actions'];
        $this->assertArrayHasKey('instrument.execute', $actions);
        $this->assertArrayNotHasKey('proposition.variant', $actions);
    }

    public function test_consumed_download_is_prepared_without_any_loading_step_and_history_stays_transient(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        Http::fake(['*' => Http::response('Downloaded preparation body sentinel', 200, ['Content-Type' => 'text/plain'])]);
        // Historical acquisition component; the operational Persist boundary is covered above.
        $receipt = $this->observationFixture($f->gate->phase, $f->gate, $this->input());
        $fixture->consume($receipt);
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input([$receipt]));
        $delivery = $service->delivery($f->viewer, $execution, 'inline-text');
        $this->assertSame('Downloaded preparation body sentinel', $delivery['deliveries'][0]['content']);
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $result = $service->defer($f->viewer, $execution, 'Need another comparison.');
        $this->assertTrue($result->isEvidence());
        $this->assertNull($result->consumption);
        $this->assertNull($f->phase->fresh()->completed_at);
        $this->assertStringNotContainsString('Downloaded preparation body sentinel', json_encode($result->metadata));
        $this->assertDatabaseMissing('participant_receipt_contexts', ['receipt_id' => $receipt->id]);
    }

    public function test_failed_download_completes_observation_but_storage_failure_does_not_spend_allowance(): void
    {
        $f = $this->fixture();
        Http::fake(['*' => Http::sequence()->push('', 404)->push('bytes')]);
        $failed = app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input());
        $this->assertSame('completed', $failed->evidenceExecution->status);
        $this->assertFalse($failed->metadata['content_verified']);
        $this->assertArrayNotHasKey('artifacts', $failed->metadata);
        $this->assertNull($failed->signature);
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->contains('id', $failed->id));
        $other = $this->fixture();
        $this->mock(ArtifactStore::class, fn ($m) => $m->shouldReceive('preserve')->andThrow(new ArtifactStorageException('Unavailable')));
        try {
            app(OperationalInstrumentService::class)->execute($other->phase, $other->gate, $this->input());
            $this->fail('Infrastructure failure recorded as observation');
        } catch (ArtifactStorageException) {
            $this->assertSame(0, EvidenceExecution::where('phase_id', $other->phase->id)->count());
            $this->assertSame(1, Receipt::count());
        }
    }

    public function test_duplicate_byte_observation_is_rechecked_and_changed_versions_remain_distinct(): void
    {
        $f = $this->fixture();
        Http::fake(['*' => Http::sequence()->push('same')->push('same')->push('changed')]);
        $receipt = app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input());
        $same = app(DownloadAcquisition::class)->acquire($this->input()['reference']);
        $changed = app(DownloadAcquisition::class)->acquire($this->input()['reference']);
        $relevant = app(RelevantInstrumentService::class);
        $this->assertNull($relevant->equivalent($f->gate, $this->input()));
        $this->assertSame($receipt->id, $relevant->equivalent($f->gate, $this->input(), download: $same)->id);
        $this->assertNull($relevant->equivalent($f->gate, $this->input(), download: $changed));
        $this->assertTrue($relevant->exclusions($f->gate)->isEmpty());
        $this->expectException(ConflictHttpException::class);
        $relevant->assertAvailable($f->gate, $this->input(), download: $same);
    }

    public function test_stale_observation_cannot_bypass_changed_receipt_availability(): void
    {
        $f = $this->fixture();
        Http::fake(['*' => Http::response('bytes')]);
        $service = app(OperationalInstrumentService::class);
        $observed = $service->observeFor($f->phase, $f->gate, $this->input());
        $this->observationFixture($f->gate->phase, $f->gate, ['kind' => 'citation', 'reference' => $f->seed->id]);
        try {
            $service->execute($f->phase, $f->gate, $this->input(), observed: $observed);
            $this->fail('Stale acquisition bypassed Receipt-governed availability');
        } catch (ConflictHttpException) {
            $this->assertSame(0, Receipt::where('type', 'load_file')->count());
            Http::assertSentCount(1);
        }
    }

    public function test_unsupported_binary_is_valid_acquisition_with_no_advertised_reader(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        Http::fake(['*' => Http::response("%PDF-1.7\0\xff", 200, ['Content-Type' => 'application/pdf'])]);
        $receipt = $this->observationFixture($f->gate->phase, $f->gate, $this->input());
        $this->assertTrue($receipt->metadata['content_verified']);
        $this->assertSame('completed', $receipt->evidenceExecution->status);
        $fixture->consume($receipt);
        $options = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['preparation_options'];
        $this->assertFalse(collect($options)->contains('receipt_id', $receipt->id));
        $this->assertTrue(app(HorizonReceiptService::class)->accessibleOrdinary($f->phase)->contains('id', $receipt->id));
    }

    public function test_download_replay_keeps_source_invocation_and_records_a_changed_exact_byte_version(): void
    {
        $f = $this->fixture();
        Http::fake(['*' => Http::sequence()->push('old bytes')->push('new bytes')]);
        $source = app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input());
        DB::transaction(fn () => $this->consumeObservationFixture($f, $source));
        $phase = DB::transaction(function () use ($f) {
            $f->phase->forceFill(['completed_at' => now()])->save();
            $f->phase->cycle->forceFill(['completed_at' => now()])->save();
            $cycle = $f->phase->cycle->replicate();
            $cycle->sequence++;
            $cycle->completed_at = null;
            $cycle->save();
            $phase = $f->phase->replicate();
            $phase->cycle_id = $cycle->id;
            $phase->completed_at = null;
            $phase->save();

            return $phase;
        });
        $result = app(OperationalInstrumentService::class)->execute($phase, $f->gate, $source->replayInvocation(), replayedFrom: $source);
        $this->assertSame($source->id, $result->evidenceExecution->replayed_from_receipt_id);
        $this->assertSame($source->replayInvocation(), $result->replayInvocation());
        $this->assertNotSame($source->signature, $result->signature);
        $this->assertNull($result->consumption);
        $this->assertSame(2, EvidenceExecution::where('kind', 'load_file')->where('status', 'completed')->count());
    }
}
