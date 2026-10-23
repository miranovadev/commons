<?php

namespace Tests\Feature;

use App\Services\Evidence\HyperlinkMetadataExtractor;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class HyperlinkReferenceTest extends TestCase
{
    private function extract(string $html, string $url = 'https://example.org/research/index.html'): array
    {
        return app(HyperlinkMetadataExtractor::class)->extract($html, 'text/html', 'Existing excerpt', false, $url);
    }

    public static function destinations(): array
    {
        return [
            ['https://other.org/doc', 'https://other.org/doc'],
            ['http://other.org:8080/doc?a=1&amp;b=2#section', 'http://other.org:8080/doc?a=1&b=2#section'],
            ['/research/paper.pdf', 'https://example.org/research/paper.pdf'],
            ['../data.csv', 'https://example.org/data.csv'],
            ['./paper.pdf', 'https://example.org/research/paper.pdf'],
            ['//cdn.example.org/paper.pdf', 'https://cdn.example.org/paper.pdf'],
            ['?dataset=1&amp;format=csv', 'https://example.org/research/index.html?dataset=1&format=csv'],
            ['/café?q=🌌', 'https://example.org/caf%C3%A9?q=%F0%9F%8C%8C'],
            ['https://例え.jp/paper', 'https://例え.jp/paper'],
            ['http://127.0.0.1/secret', 'http://127.0.0.1/secret'], // Reference, never acquisition authority.
        ];
    }

    #[DataProvider('destinations')]
    public function test_resolution_preserves_navigation_identity(string $href, string $expected): void
    {
        $result = $this->extract('<a href="'.$href.'">Destination</a>');
        $this->assertSame([['url' => $expected, 'text' => 'Destination']], $result['links']);
        $this->assertSame(1, $result['links_count']);
        $this->assertFalse($result['links_truncated']);
    }

    public static function excluded(): array
    {
        return array_map(fn ($href) => [$href], ['', '  ', '#section', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)',
            'data:text/html,hi', 'file:///tmp/file', 'mailto:me@example.org', 'tel:123', 'ftp://example.org/a',
            'https://', 'http:80', 'https:123', 'http:paper', 'https:/paper', 'http://[invalid]/', 'https://bad host/a', 'https://user:pass@example.org/a',
            'https://example.org/%XX', 'https://example.org\\evil', "https://example.org/\ninvalid"]);
    }

    #[DataProvider('excluded')]
    public function test_empty_invalid_and_unsupported_destinations_are_excluded(string $href): void
    {
        $result = $this->extract('<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">Excluded</a>');
        $this->assertSame([], $result['links']);
        $this->assertSame(0, $result['links_count']);
        $this->assertFalse($result['links_truncated']);
        $this->assertArrayNotHasKey('links_error', $result);
    }

    public function test_text_entities_unicode_empty_labels_and_first_occurrence_order(): void
    {
        $html = '<a href="/one"> Read the <strong>technical paper</strong> &amp; café&nbsp;🌌 </a>'
            .'<a href="https://example.org/one">Later duplicate</a>'
            .'<a href="/two"><img alt="Do not invent a label" src="/image"></a>'
            .'<a href="/three">&amp;amp;<script>not text</script><style>also not text</style><template>inert</template></a>'
            .'<template><a href="/inert">Not a navigable document anchor</a></template>'
            .'<a href="/one?version=2">Query differs</a><a href="/one?version=3">Another query</a>';
        $result = $this->extract($html);
        $this->assertSame([
            ['url' => 'https://example.org/one', 'text' => 'Read the technical paper & café 🌌'],
            ['url' => 'https://example.org/two', 'text' => ''],
            ['url' => 'https://example.org/three', 'text' => '&amp;'],
            ['url' => 'https://example.org/one?version=2', 'text' => 'Query differs'],
            ['url' => 'https://example.org/one?version=3', 'text' => 'Another query'],
        ], $result['links']);
        $this->assertSame(5, $result['links_count']);
        $this->assertSame($result, $this->extract($html));
    }

    public function test_count_and_text_bounds_include_unique_references_after_the_retained_prefix(): void
    {
        $html = '';
        for ($i = 0; $i < 47; $i++) {
            $html .= '<a href="/'.$i.'">'.str_repeat('界', 201).'</a><a href="/'.$i.'">Duplicate</a>';
        }
        $result = $this->extract($html);
        // Short ASCII labels exercise the count ceiling independently of JSON bytes.
        $countResult = $this->extract(str_replace(str_repeat('界', 201), 'Label', $html));
        $this->assertCount(25, $countResult['links']);
        $this->assertSame('https://example.org/24', $countResult['links'][24]['url']);
        $this->assertSame(47, $countResult['links_count']);
        $this->assertTrue($countResult['links_truncated']);
        $this->assertSame(str_repeat('界', 200), $result['links'][0]['text']);
        $this->assertSame(47, $result['links_count']);
        $this->assertTrue($result['links_truncated']);
        $this->assertLessThanOrEqual(HyperlinkMetadataExtractor::LINKS_JSON_LIMIT,
            strlen(json_encode($result['links'], JSON_UNESCAPED_SLASHES)));
        $exact = $this->extract(implode('', array_map(fn ($i) => '<a href="/'.$i.'">Label</a>', range(0, 24))));
        $this->assertCount(25, $exact['links']);
        $this->assertFalse($exact['links_truncated']);
    }

    public function test_oversized_urls_are_omitted_without_changing_the_destination_and_budget_retains_a_prefix(): void
    {
        $long = 'https://example.org/'.str_repeat('x', 2048);
        $result = $this->extract('<a href="'.$long.'">Long</a><a href="/short">Short</a>');
        $this->assertSame([['url' => 'https://example.org/short', 'text' => 'Short']], $result['links']);
        $this->assertSame(2, $result['links_count']);
        $this->assertTrue($result['links_truncated']);
        $html = implode('', array_map(fn ($i) => '<a href="/'.str_repeat('a', 1800).$i.'">'.str_repeat('🌌', 200).'</a>', range(1, 10)));
        $bounded = $this->extract($html.'<a href="/late">Late</a>');
        $this->assertSame(11, $bounded['links_count']);
        $this->assertTrue($bounded['links_truncated']);
        $this->assertLessThan(10, count($bounded['links']));
        $this->assertNotContains('https://example.org/late', array_column($bounded['links'], 'url'));
        $this->assertLessThanOrEqual(HyperlinkMetadataExtractor::LINKS_JSON_LIMIT,
            strlen(json_encode($bounded['links'], JSON_UNESCAPED_SLASHES)));
    }

    public function test_malformed_html_and_no_links_are_successful_descriptions_and_base_tags_are_not_authority(): void
    {
        $result = $this->extract('<base href="https://other.invalid/"><p>Readable<a href="../paper">Unclosed <b>label');
        $this->assertSame([['url' => 'https://example.org/paper', 'text' => 'Unclosed label']], $result['links']);
        $empty = $this->extract('<p>Readable source without references</p>');
        $this->assertSame([], $empty['links']);
        $this->assertSame(0, $empty['links_count']);
        $this->assertFalse($empty['links_truncated']);
    }

    public function test_redirect_base_exact_artifact_and_fingerprint_without_secondary_requests(): void
    {
        $this->app->bind(HyperlinkVerifier::class, HyperlinkVerifier::class);
        $this->mock(PublicResourceAddress::class, fn ($m) => $m->shouldReceive('resolve')->twice()->andReturn('93.184.216.34'));
        Storage::fake('artifacts');
        Http::preventStrayRequests();
        $html = '<html><head><meta name="description" content="Short description"></head><body><a href="../data.csv?q=1&amp;q=2">Dataset</a><img src="https://never.example/image"><script src="https://never.example/script">doNotExecute()</script></body></html>';
        Http::fake(['https://example.org/start' => Http::response('', 302, ['Location' => 'https://final.example/research/index.html']),
            'https://final.example/research/index.html' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        $verified = app(HyperlinkVerifier::class)->verify('https://example.org/start');
        $this->assertSame([['url' => 'https://final.example/data.csv?q=1&q=2', 'text' => 'Dataset']], $verified->metadata()['links']);
        $this->assertSame('Short description', $verified->metadata()['description']);
        $this->assertSame(hash('sha256', 'Dataset'), $verified->fingerprint);
        $this->assertSame('sha256:'.hash('sha256', $html), $verified->artifacts[0]['hash']);
        $this->assertSame($html, Storage::disk('artifacts')->get($verified->artifacts[0]['path']));
        Http::assertSentCount(2);
    }

    public function test_supplementary_exception_publishes_no_partial_links_and_does_not_fail_verification(): void
    {
        $this->app->bind(HyperlinkVerifier::class, HyperlinkVerifier::class);
        $this->mock(PublicResourceAddress::class, fn ($m) => $m->shouldReceive('resolve')->once()->andReturn('93.184.216.34'));
        $this->partialMock(HyperlinkMetadataExtractor::class, fn ($m) => $m->shouldAllowMockingProtectedMethods()
            ->shouldReceive('references')->once()->andThrow(new RuntimeException('Untrusted internal diagnostic')));
        Storage::fake('artifacts');
        Http::preventStrayRequests();
        Http::fake(['https://example.org/page' => Http::response('<title>Title</title><a href="/one">One</a>', 200, ['Content-Type' => 'text/html'])]);
        $verified = app(HyperlinkVerifier::class)->verify('https://example.org/page');
        $metadata = $verified->metadata();
        $this->assertTrue($metadata['content_verified']);
        $this->assertSame('Title', $metadata['title']);
        $this->assertSame(hash('sha256', 'TitleOne'), $verified->fingerprint);
        $this->assertSame([], $metadata['links']);
        $this->assertNull($metadata['links_count']);
        $this->assertSame('html_reference_extraction_failed', $metadata['links_error']);
        $this->assertStringNotContainsString('Untrusted internal diagnostic', json_encode($metadata));
        Http::assertSentCount(1);
    }
}
