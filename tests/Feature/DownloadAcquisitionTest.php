<?php

namespace Tests\Feature;

use App\Exceptions\ArtifactStorageException;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\DownloadAcquisition;
use App\Services\Evidence\InstrumentInvocation;
use App\Services\Evidence\PublicResourceAddress;
use DomainException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DownloadAcquisitionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(PublicResourceAddress::class, fn ($m) => $m->shouldReceive('resolve')->andReturn('93.184.216.34'));
        Http::preventStrayRequests();
        Storage::fake('artifacts');
    }

    public static function resources(): array
    {
        return [
            'binary' => ["%PDF-1.7\0\xff\r\n", 'application/pdf'],
            'text' => ["  Unicode 💚\r\nUnchanged\t", 'text/plain'],
            'html' => ['<title>Not a description</title><a href="/never">No fetch</a><script>throw 1</script>', 'text/html'],
            'source' => ['<?php file_put_contents("/tmp/should-never-execute", "no");', 'text/x-php'],
            'empty' => ['', 'application/octet-stream'],
        ];
    }

    #[DataProvider('resources')]
    public function test_preserves_bytes_without_describing_or_executing_them(string $body, string $type): void
    {
        Http::fake(['*' => Http::response($body, 200, ['Content-Type' => $type.'; charset=utf-8'])]);
        $result = app(DownloadAcquisition::class)->acquire('https://example.com/file?version=1&sig=a%2Fb');
        $this->assertTrue($result->successful());
        $a = $result->metadata['artifacts'][0];
        $this->assertSame($body, Storage::disk('artifacts')->get($a['path']));
        $this->assertSame('sha256:'.hash('sha256', $body), $a['hash']);
        $this->assertSame(strlen($body), $a['size']);
        $this->assertSame($type, $a['media_type']);
        $this->assertSame('file', $result->metadata['filename']);
        $this->assertIsInt($result->metadata['processing_ms']);
        $this->assertGreaterThanOrEqual(0, $result->metadata['processing_ms']);
        $this->assertLessThanOrEqual(2147483647, $result->metadata['processing_ms']);
        foreach (['links', 'title', 'description', 'content_excerpt', 'fingerprint', 'last_modified'] as $field) {
            $this->assertArrayNotHasKey($field, $result->metadata);
        }
        Http::assertSent(fn ($request) => $request->url() === $result->sourceUrl);
        Http::assertSentCount(1);
    }

    public function test_redirects_keep_query_and_record_safe_filename_and_final_address(): void
    {
        Http::fake(['https://example.com/start?token=x' => Http::response('', 302, ['Location' => '/files/data?edition=2']),
            'https://example.com/files/data?edition=2' => Http::response('bytes', 200,
                ['Content-Type' => 'invalid', 'Content-Disposition' => "attachment; filename*=UTF-8''..%2F..%2F%E7%A0%94%E7%A9%B6.csv"])]);
        $result = app(DownloadAcquisition::class)->acquire('https://example.com/start?token=x#section');
        $this->assertSame('https://example.com/start?token=x', $result->sourceUrl);
        $this->assertSame('https://example.com/files/data?edition=2', $result->metadata['effective_url']);
        $this->assertSame('研究.csv', $result->metadata['filename']);
        $this->assertSame('application/octet-stream', $result->metadata['artifacts'][0]['media_type']);
        Http::assertSentCount(2);
    }

    public function test_storage_dedup_and_resource_version_identity_are_independent(): void
    {
        Http::fake(['*' => Http::sequence()->push('same')->push('same')->push('same')->push('changed')]);
        $service = app(DownloadAcquisition::class);
        $a = $service->acquire('https://example.com/file?a=1');
        $b = $service->acquire('https://example.com/file?a=1');
        $other = $service->acquire('https://example.com/file?a=2');
        $changed = $service->acquire('https://example.com/file?a=1');
        $this->assertSame($a->identity(), $b->identity());
        $this->assertNotSame($a->identity(), $other->identity());
        $this->assertNotSame($a->identity(), $changed->identity());
        $this->assertSame($a->metadata['artifacts'], $other->metadata['artifacts']);
        $this->assertNotSame($a->metadata['artifacts'][0]['hash'], $changed->metadata['artifacts'][0]['hash']);
        $this->assertCount(2, Storage::disk('artifacts')->allFiles());
    }

    public static function invalidInputs(): array
    {
        return array_map(fn ($input) => [$input], [
            ['kind' => 'load_file', 'method' => 'upload', 'reference' => 'https://example.com/a'],
            ['kind' => 'load_file', 'reference' => 'https://example.com/a'],
            ...array_map(fn ($url) => ['kind' => 'load_file', 'method' => 'download', 'reference' => $url],
                ['file:///etc/passwd', 'http://user:pass@example.com/a', 'https://example.com:8443/a', 'not a URL', 'https://example.com/with space', 'https://example.com/'.str_repeat('a', 4096)]),
        ]);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_invocations_are_rejected_before_acquisition(array $input): void
    {
        $this->expectException(DomainException::class);
        app(InstrumentInvocation::class)->normalize($input);
    }

    public static function failedResponses(): array
    {
        return [[404, [], 'missing'], [206, [], 'partial'], [200, ['Content-Encoding' => 'gzip'], 'encoded'],
            [302, [], 'no redirect location'], [200, [], str_repeat('a', 65)]];
    }

    #[DataProvider('failedResponses')]
    public function test_retrieval_failures_do_not_claim_an_artifact(int $status, array $headers, string $body): void
    {
        config(['artifacts.download_max_bytes' => 64]);
        Http::fake(['*' => Http::response($body, $status, $headers)]);
        $result = app(DownloadAcquisition::class)->acquire('https://example.com/file');
        $this->assertFalse($result->successful());
        $this->assertSame('download-failed', $result->metadata['validation']);
        $this->assertArrayNotHasKey('artifacts', $result->metadata);
        $this->assertCount(0, Storage::disk('artifacts')->allFiles());
        Http::assertSentCount(1);
    }

    public function test_redirect_limit_and_timeout_are_failed_observations(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => '/again'])]);
        $this->assertFalse(app(DownloadAcquisition::class)->acquire('https://example.com/start')->successful());
        Http::assertSentCount(4);
        Http::fake(fn () => throw new ConnectionException('Timeout'));
        $this->assertFalse(app(DownloadAcquisition::class)->acquire('https://example.com/start')->successful());
    }

    public function test_private_redirect_and_credentials_never_receive_a_request(): void
    {
        $this->app->instance(PublicResourceAddress::class, new PublicResourceAddress);
        Http::fake(['http://93.184.216.34/file' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret'])]);
        $this->assertFalse(app(DownloadAcquisition::class)->acquire('http://93.184.216.34/file')->successful());
        Http::assertSentCount(1);
    }

    public function test_transport_enforces_bounds_during_transfer_not_just_after_buffering(): void
    {
        config(['artifacts.download_max_bytes' => 64]);
        $options = null;
        Http::fake(function ($request, $actual) use (&$options) {
            $options = $actual;
            ($actual['progress'])(0, 64, 0, 0);
            ($actual['progress'])(0, 65, 0, 0); // unknown-length stream must abort here

            return Http::response('Should never reach a completed response');
        });
        $this->assertFalse(app(DownloadAcquisition::class)->acquire('https://example.com/file')->successful());
        $this->assertFalse($options['allow_redirects']);
        $this->assertTrue($options['verify']);
        $this->assertFalse($options['decode_content']);
        $this->assertSame('', $options['proxy']);
        $this->assertSame(['example.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(3, $options['connect_timeout']);
        $this->assertSame(10, $options['timeout']);
        foreach ([new Response(200, ['Content-Length' => '65']), new Response(200, ['Content-Length' => 'nonsense'])] as $response) {
            try {
                ($options['on_headers'])($response);
                $this->fail('Invalid or oversized Content-Length accepted');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertCount(0, Storage::disk('artifacts')->allFiles());
    }

    public function test_storage_failure_is_not_a_completed_resource_observation(): void
    {
        Http::fake(['*' => Http::response('bytes')]);
        $this->mock(ArtifactStore::class, fn ($m) => $m->shouldReceive('preserve')->once()->andThrow(new ArtifactStorageException('Unavailable')));
        $this->expectException(ArtifactStorageException::class);
        app(DownloadAcquisition::class)->acquire('https://example.com/file');
    }
}
