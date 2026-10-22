<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\ReceiptCapability;
use App\Exceptions\ArtifactStorageException;
use App\Models\ParticipantReceiptContext;
use App\Models\Receipt;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceAddress;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\InstrumentExecutionService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class HyperlinkArtifactReceiptTest extends TestCase
{
    // Real observation must run at transaction level zero, unlike transaction-wrapped fixtures.
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        // Match TransactionLockOrderTest: history-preserving down migrations must
        // not be used to clean committed fixtures. The next test starts fresh.
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(HyperlinkVerifier::class, HyperlinkVerifier::class);
        $this->mock(PublicResourceAddress::class, fn ($m) => $m->shouldReceive('resolve')->andReturn('93.184.216.34'));
        Http::preventStrayRequests();
        Storage::fake('artifacts');
    }

    private function persistFixture(): ParticipantContextFixture
    {
        $f = DB::transaction(fn () => new ParticipantContextFixture(1, 0, false));
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'write_register' => 'frontier'])->save();

        return $f;
    }

    private function input(string $path = 'page'): array
    {
        return ['kind' => 'hyperlink', 'reference' => 'https://example.com/'.$path];
    }

    public function test_real_contribution_preserves_bytes_but_only_projects_bounded_metadata(): void
    {
        $f = $this->persistFixture();
        $target = DB::transaction(fn () => $f->node($f->seed, 'activity', position: PhaseKind::Distinguish));
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $target->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $target->id])->save();
        $html = '<html><head><title>🌌 Title &amp; more</title><meta name="description" content="'.str_repeat('界', 1300).'"></head><body><a href="https://example.com/out">EXACT ORIGINAL LINK</a><script>privateArtifactSentinel()</script>'.str_repeat(' Full body.', 5000).'</body></html>';
        $store = app(ArtifactStore::class);
        $this->mock(ArtifactStore::class, function ($mock) use ($store) {
            $mock->shouldReceive('preserve')->once()->andReturnUsing(function ($body, $type) use ($store) {
                $this->assertSame(0, DB::transactionLevel());

                return $store->preserve($body, $type);
            });
        });
        Http::fake(function () use ($html) {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        });
        $this->publishPotentialFixture($f->viewer);
        $state = app(ParticipationService::class)->contribute($f->viewer, $f->phase->id,
            ['action' => 'instrument.execute', 'proposition_id' => $target->id, 'instrument' => $this->input()]);
        $receipt = Receipt::where('type', 'hyperlink')->sole();
        $metadata = $receipt->metadata;
        $artifact = $metadata['artifacts'][0];
        $this->assertSame($html, Storage::disk('artifacts')->get($artifact['path']));
        $this->assertSame('sha256:'.hash('sha256', $html), $artifact['hash']);
        $this->assertSame(strlen($html), $artifact['size']);
        $this->assertSame('text/html', $artifact['media_type']);
        $this->assertSame('🌌 Title & more', $metadata['title']);
        $this->assertSame(str_repeat('界', 1200), $metadata['description']);
        $this->assertSame('meta_description', $metadata['description_source']);
        $this->assertTrue($receipt->supports(ReceiptCapability::Readable));
        $this->assertTrue($receipt->supports(ReceiptCapability::Replayable));
        $this->assertSame($metadata, $receipt->instrumentExecution->metadata);
        $this->assertDatabaseHas('participant_receipt_contexts', ['receipt_id' => $receipt->id, 'participant_id' => $f->viewer->id, 'cleared_at' => null]);
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        $inline = collect($packet['receipts'])->firstWhere('id', $receipt->id)['loaded_inline_content'];
        $this->assertSame($metadata['description'], $inline['content_excerpt']);
        $this->assertSame($metadata['title'], $inline['title']);
        $json = json_encode($packet);
        $this->assertStringNotContainsString('artifacts/sha256', $json);
        $this->assertStringNotContainsString('privateArtifactSentinel', $json);
        $this->assertStringNotContainsString('EXACT ORIGINAL LINK', $json);
        $content = app(ReceiptEvidenceService::class)->observation($receipt)['evidence'];
        $this->assertSame(str_repeat('界', 1200), $content['content_excerpt']);
        $this->assertArrayNotHasKey('artifacts', $content);
        $this->assertArrayNotHasKey('description', $content);
        $this->assertLessThanOrEqual(300, mb_strlen($content['title']));
    }

    public function test_distinct_receipts_share_one_artifact_without_changing_duplicate_suppression(): void
    {
        $f = $this->persistFixture();
        Http::fake(['*' => Http::response('<h1>Same bytes</h1>', 200, ['Content-Type' => 'text/html'])]);
        $service = app(OperationalInstrumentService::class);
        $a = $service->execute($f->phase, $f->gate, $this->input());
        $other = $this->persistFixture();
        $b = $service->execute($other->phase, $other->gate, $this->input());
        $this->assertNotSame($a->id, $b->id);
        $this->assertSame($a->metadata['artifacts'], $b->metadata['artifacts']);
        $this->assertSame($a->signature, $b->signature);
        $this->assertCount(1, Storage::disk('artifacts')->allFiles());
        try {
            $service->execute($f->phase, $f->gate, $this->input());
            $this->fail('Duplicate observation accepted');
        } catch (ConflictHttpException) {
            Http::assertSentCount(2);
            $this->assertDatabaseCount('receipts', 2);
        }
    }

    public function test_byte_changes_do_not_replace_normalized_identity_or_the_seven_day_clock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20T00:00:00Z'));
        $f = $this->persistFixture();
        Http::fake(['*' => Http::sequence()
            ->push('<h1>Stable text</h1><script>1</script>', 200, ['Content-Type' => 'text/html'])
            ->push('<div> Stable  text </div><script>2</script>', 200, ['Content-Type' => 'text/html'])
            ->push('<p>Changed text</p>', 200, ['Content-Type' => 'text/html'])
            ->push('<p>Changed text</p>', 200, ['Content-Type' => 'text/html'])]);
        $observe = fn () => $this->observationFixture($f->phase,
            DB::transaction(fn () => $f->node($f->gate, 'frontier', position: PhaseKind::Mediate)), $this->input());
        $a = $observe();
        $b = $observe();
        $this->assertSame(hash('sha256', 'Stable text'), $a->metadata['fingerprint']);
        $this->assertSame($a->signature, $b->signature);
        $this->assertNotSame($a->metadata['artifacts'], $b->metadata['artifacts']);
        $c = $observe();
        $this->assertNull($c->signature);
        $this->assertSame('version-awaiting-stability', $c->metadata['reinforcement_version']);
        $this->travel(7)->days();
        $d = $observe();
        $this->assertNotNull($d->signature);
        $this->assertNotSame($a->signature, $d->signature);
        $this->assertSame($c->metadata['artifacts'], $d->metadata['artifacts']);
        $this->assertNull($c->fresh()->signature);
        $this->assertCount(3, Storage::disk('artifacts')->allFiles());
    }

    public static function retrievalFailures(): array
    {
        return [['http'], ['empty'], ['partial'], ['oversize'], ['compressed'], ['no-verifiable-text'], ['connection']];
    }

    #[DataProvider('retrievalFailures')]
    public function test_failed_observations_never_preserve_artifacts(string $mode): void
    {
        $f = $this->persistFixture();
        Http::fake(['*' => match ($mode) {
            'http' => Http::response('Not found', 404),
            'empty' => Http::response('', 200),
            'partial' => Http::response('Partial', 206),
            'oversize' => Http::response(str_repeat('a', HyperlinkVerifier::MAX_BYTES + 1)),
            'compressed' => Http::response('compressed', 200, ['Content-Encoding' => 'gzip']),
            'no-verifiable-text' => Http::response('<script>only script</script>', 200, ['Content-Type' => 'text/html']),
            'connection' => Http::failedConnection(),
        }]);
        $receipt = app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input());
        $this->assertFalse($receipt->metadata['content_verified']);
        $this->assertSame('verification-failed', $receipt->metadata['validation']);
        $this->assertArrayNotHasKey('artifacts', $receipt->metadata);
        $this->assertNull($receipt->signature);
        $this->assertCount(0, Storage::disk('artifacts')->allFiles());
        $this->assertDatabaseHas('participant_receipt_contexts', ['receipt_id' => $receipt->id]);
    }

    public function test_storage_failure_is_an_execution_error_without_receipt_or_auto_load(): void
    {
        $f = $this->persistFixture();
        Http::fake(['*' => Http::response('Valid content', 200, ['Content-Type' => 'text/plain'])]);
        $this->mock(ArtifactStore::class, fn ($m) => $m->shouldReceive('preserve')->once()->andThrow(new ArtifactStorageException('Artifact persistence failed.')));
        try {
            app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input());
            $this->fail('Storage failure became an observation');
        } catch (ArtifactStorageException) {
            $this->assertDatabaseCount('receipts', 0);
            $this->assertDatabaseCount('instrument_executions', 0);
            $this->assertDatabaseCount('participant_receipt_contexts', 0);
        }
    }

    public function test_completion_rollback_leaves_only_the_immutable_artifact(): void
    {
        $f = $this->persistFixture();
        Http::fake(['*' => Http::response('Retained after rollback', 200, ['Content-Type' => 'text/plain'])]);
        ParticipantReceiptContext::created(function () {
            throw new RuntimeException('completion failure');
        });
        try {
            app(OperationalInstrumentService::class)->execute($f->phase, $f->gate, $this->input());
            $this->fail('Completion failure did not propagate');
        } catch (RuntimeException $error) {
            $this->assertSame('completion failure', $error->getMessage());
            $this->assertDatabaseCount('receipts', 0);
            $this->assertDatabaseCount('instrument_executions', 0);
            $this->assertDatabaseCount('participant_receipt_contexts', 0);
            $this->assertDatabaseCount('reinforcement_sources', 0);
            $paths = Storage::disk('artifacts')->allFiles();
            $this->assertCount(1, $paths);
            $this->assertSame('Retained after rollback', Storage::disk('artifacts')->get($paths[0]));
        }
    }

    public function test_binary_and_malformed_media_headers_keep_existing_opaque_evidence_contract(): void
    {
        $types = ['application/pdf', 'application/octet-stream', 'invalid header', ''];
        $sequence = Http::sequence();
        foreach ($types as $type) {
            $sequence->push("%PDF-1.7\n\x00\xff", 200, ['Content-Type' => $type]);
        }
        Http::fake(['*' => $sequence]);
        foreach ($types as $type) {
            $verified = app(HyperlinkVerifier::class)->verify('https://example.com/opaque');
            $this->assertSame(hash('sha256', "%PDF-1.7\n\x00\xff"), $verified->fingerprint);
            $this->assertNull($verified->contentExcerpt);
            $this->assertNull($verified->metadata()['description']);
            $this->assertSame($type === 'application/pdf' ? $type : 'application/octet-stream', $verified->artifacts[0]['media_type']);
        }
    }

    public function test_historical_receipt_without_artifacts_keeps_its_existing_readable_projection(): void
    {
        $f = $this->persistFixture();
        $execution = app(InstrumentExecutionService::class)->start($f->phase, $f->gate, 'hyperlink', 'https://example.com/old', $this->input('old'));
        $execution->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $receipt = (new Receipt)->forceFill(['instrument_execution_id' => $execution->id,
            'type' => 'hyperlink', 'reference' => $execution->reference, 'signature' => 'historical-signature',
            'metadata' => ['content_verified' => true, 'reinforcement_version' => 'accepted-version',
                'resource' => 'example.com/old', 'content_excerpt' => 'Historical excerpt', 'excerpt_truncated' => false]]);
        $receipt->receiptable()->associate($f->gate);
        $receipt->save();
        $receipt = $receipt->fresh();
        $before = $receipt->getAttributes();
        $this->assertTrue($receipt->supports(ReceiptCapability::Readable));
        $this->assertTrue($receipt->supports(ReceiptCapability::Replayable));
        $this->assertSame(['signature' => 'historical-signature', 'evidence' => [
            'resource' => 'example.com/old', 'content_excerpt' => 'Historical excerpt', 'excerpt_truncated' => false,
        ]], app(ReceiptEvidenceService::class)->observation($receipt));
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $this->assertCount(0, Storage::disk('artifacts')->allFiles());
    }
}
