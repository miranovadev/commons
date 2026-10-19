<?php

namespace Tests\Feature;

use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceAddress;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HyperlinkVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(HyperlinkVerifier::class, HyperlinkVerifier::class);
        $this->mock(PublicResourceAddress::class, fn ($mock) => $mock->shouldReceive('resolve')->andReturn('93.184.216.34'));
        Http::preventStrayRequests();
        Storage::fake('artifacts');
    }

    public static function equivalents(): array
    {
        return [
            ['https://EXAMPLE.com/article?x=1#y', 'example.com/article'],
            ['http://example.com:80/article', 'example.com/article'],
            ['https://example.com:443/article', 'example.com/article'],
            ['https://example.com./a/../%61rticle', 'example.com/article'],
            ['https://example.com', 'example.com/'],
            ['https://example.com/%7eclaire', 'example.com/~claire'],
            ['https://example.com/a%2fb', 'example.com/a%2Fb'],
        ];
    }

    #[DataProvider('equivalents')]
    public function test_normalization(string $url, string $expected): void
    {
        $this->assertSame($expected, app(HyperlinkIdentity::class)->resource($url));
    }

    public function test_verifies_canonical_path_and_keeps_server_observed_and_http_dates_separate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01T00:00:00Z'));
        Http::fake(['https://example.com/article' => Http::response('<h1>Stable evidence</h1>', 200,
            ['Content-Type' => 'text/html', 'Last-Modified' => 'Tue, 01 Sep 2026 00:00:00 GMT'])]);
        $result = app(HyperlinkVerifier::class)->verify('https://EXAMPLE.com/article?id=123#fragment');
        $this->assertSame('example.com/article', $result->resource);
        $this->assertSame(hash('sha256', 'Stable evidence'), $result->fingerprint);
        $this->assertSame('2026-09-01T00:00:00+00:00', $result->lastModified->toIso8601String());
        $this->assertSame('server-observed', $result->metadata()['version_clock']);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/article');
    }

    public function test_missing_invalid_and_future_modification_times_do_not_invent_precision(): void
    {
        foreach (['', 'tomorrow', 'Tue, 01 Sep 2099 00:00:00 GMT'] as $header) {
            Http::fake(['*' => Http::response('A resource', 200, ['Content-Type' => 'text/plain', 'Last-Modified' => $header])]);
            $result = app(HyperlinkVerifier::class)->verify('https://example.com/page');
            $this->assertNull($result->lastModified);
            $this->assertNotNull($result->observedAt);
        }
    }

    public function test_cosmetic_html_and_script_changes_preserve_fingerprint(): void
    {
        Http::fake(['*' => Http::sequence()->push('<h1>Stable text</h1><script>time=1</script>', 200, ['Content-Type' => 'text/html'])
            ->push('<div>  Stable   text </div><script>time=2</script>', 200, ['Content-Type' => 'text/html'])]);
        $verifier = app(HyperlinkVerifier::class);
        $this->assertSame($verifier->verify('https://example.com/page')->fingerprint, $verifier->verify('https://example.com/page')->fingerprint);
    }

    public function test_description_metadata_changes_do_not_create_new_evidence_identity(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<meta name="description" content="First"><p>Stable</p>', 200, ['Content-Type' => 'text/html'])
            ->push('<meta name="description" content="Second"><p>Stable</p>', 200, ['Content-Type' => 'text/html'])]);
        $a = app(HyperlinkVerifier::class)->verify('https://example.com/page');
        $b = app(HyperlinkVerifier::class)->verify('https://example.com/page');
        $this->assertSame(hash('sha256', 'Stable'), $a->fingerprint);
        $this->assertSame($a->fingerprint, $b->fingerprint);
        $this->assertNotSame($a->artifacts, $b->artifacts);
        $this->assertSame('First', $a->metadata()['description']);
        $this->assertSame('Second', $b->metadata()['description']);
    }

    public function test_descriptor_media_type_normalization_does_not_change_the_legacy_fingerprint_path(): void
    {
        $html = '<title>Title</title><p>Body</p>';
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html ; charset=utf-8'])]);
        $result = app(HyperlinkVerifier::class)->verify('https://example.com/page');
        // The old verifier does not strip markup when the token contains a trailing
        // space. Preserve that identity while using normalized MIME for description.
        $this->assertSame(hash('sha256', $html), $result->fingerprint);
        $this->assertSame('text/html', $result->artifacts[0]['media_type']);
        $this->assertSame('Title', $result->metadata()['title']);
        $this->assertSame('Body', $result->metadata()['description']);
    }

    public function test_redirects_resolve_to_final_identity(): void
    {
        Http::fake(['https://example.com/old' => Http::response('', 301, ['Location' => '/new?track=x']),
            'https://example.com/new' => Http::response('Resource', 200)]);
        $this->assertSame('example.com/new', app(HyperlinkVerifier::class)->verify('https://example.com/old')->resource);
    }

    public function test_redirect_destination_is_checked_before_fetching(): void
    {
        $addresses = new PublicResourceAddress;
        $this->app->instance(PublicResourceAddress::class, $addresses);
        Http::fake(['http://93.184.216.34/page' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret'])]);
        try {
            app(HyperlinkVerifier::class)->verify('http://93.184.216.34/page');
            $this->fail('Private redirect accepted.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('public Internet', $error->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_address_policy_rejects_private_loopback_mapped_and_reserved_ranges(): void
    {
        $policy = new PublicResourceAddress;
        foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '192.168.1.1', '100.64.0.1', '::1', '::ffff:127.0.0.1', 'fc00::1', '2001:db8::1'] as $address) {
            $this->assertFalse($policy->isPublic($address), $address);
        }
        $this->assertTrue($policy->isPublic('93.184.216.34'));
    }

    public function test_transport_pins_dns_disables_proxy_redirects_and_decompression_and_bounds_downloads(): void
    {
        Http::fake(function ($request, array $options) {
            $this->assertSame(['example.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
            $this->assertSame('', $options['proxy']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertFalse($options['decode_content']);
            $this->assertSame(10, $options['timeout']);
            $this->assertSame(3, $options['connect_timeout']);
            $this->assertTrue($options['verify'] ?? true);
            ($options['progress'])(0, HyperlinkVerifier::MAX_BYTES + 1);
        });
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('size limit');
        app(HyperlinkVerifier::class)->verify('https://example.com/page');
    }

    public static function unsuccessfulResponses(): array
    {
        return [['body', 404], ['', 200], ['body', 206], [str_repeat('x', HyperlinkVerifier::MAX_BYTES + 1), 200]];
    }

    #[DataProvider('unsuccessfulResponses')]
    public function test_unsuccessful_empty_partial_and_oversize_responses_cannot_verify(string $body, int $status): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        $this->expectException(DomainException::class);
        app(HyperlinkVerifier::class)->verify('https://example.com/page');
    }
}
