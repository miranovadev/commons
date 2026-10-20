<?php

namespace Tests\Feature;

use App\Services\Evidence\HyperlinkMetadataExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HyperlinkMetadataTest extends TestCase
{
    public static function html(): array
    {
        return [
            'independent priorities' => ['<title> Original &amp; title </title><meta property="og:title" content="Ignored"><h1>Heading</h1><meta name="description" content="  Actual &quot;wording&quot; &amp; spaces  "><meta property="og:description" content="Ignored">', 'Original & title', 'html_title', 'Actual "wording" & spaces', 'meta_description'],
            'open graph' => ['<title> </title><meta property="og:title" content="OG title"><meta name="description" content=" "><meta property="og:description" content="OG description"><h1>Heading</h1>', 'OG title', 'og_title', 'OG description', 'og_description'],
            'h1 and body' => ['<h1> </h1><h1>A heading</h1><p> Body   text </p><script>not evidence</script><style>not evidence</style>', 'A heading', 'h1', 'A heading Body text', 'body_text'],
            'title only' => ['<html><head><title>Title</title></head><body></body></html>', 'Title', 'html_title', null, null],
            'description only' => ['<meta name="description" content="Standalone description">', null, null, 'Standalone description', 'meta_description'],
            'unicode' => ['<title>理解 🌌 café</title><meta name="description" content="คงคำเดิม — naïve 🦋">', '理解 🌌 café', 'html_title', 'คงคำเดิม — naïve 🦋', 'meta_description'],
            'malformed HTML' => ['<title>Recovered</title><body><h1>Heading<p>Unclosed <b>body', 'Recovered', 'html_title', 'HeadingUnclosed body', 'body_text'],
            'first nonempty values' => ['<meta name="description" content=""><meta name="DESCRIPTION" content="First"><meta name="description" content="Second"><h1>&nbsp;</h1><h1>Chosen</h1>', 'Chosen', 'h1', 'First', 'meta_description'],
            'literal encoded entity' => ['<title>&amp;amp;</title><meta name="description" content="Keep  two spaces &amp; literal &amp;lt;">', '&amp;', 'html_title', 'Keep  two spaces & literal &lt;', 'meta_description'],
            'empty candidates' => ['<title> </title><meta name="description" content="&nbsp;"><p>Fallback</p>', null, null, 'Fallback', 'body_text'],
        ];
    }

    #[DataProvider('html')]
    public function test_independent_deterministic_metadata(string $html, ?string $title, ?string $titleSource, ?string $description, ?string $source): void
    {
        $result = app(HyperlinkMetadataExtractor::class)->extract($html, 'text/html', 'Old excerpt', false);
        $this->assertSame($title, $result['title']);
        $this->assertSame($titleSource, $result['title_source']);
        $this->assertSame($description, $result['description']);
        $this->assertSame($source, $result['description_source']);
        $this->assertFalse($result['description_truncated']);
    }

    public function test_unicode_limits_and_exact_source_prefixes(): void
    {
        $result = app(HyperlinkMetadataExtractor::class)->extract('<title>'.str_repeat('界', 301).'</title><meta name="description" content="'.str_repeat('🌌', 1201).'">', 'text/html', null, false);
        $this->assertSame(str_repeat('界', 300), $result['title']);
        $this->assertSame(str_repeat('🌌', 1200), $result['description']);
        $this->assertTrue($result['description_truncated']);
        $body = app(HyperlinkMetadataExtractor::class)->extract('<body>'.str_repeat('界', 1201).'</body>', 'text/html', null, false);
        $this->assertSame(str_repeat('界', 1200), $body['description']);
        $this->assertTrue($body['description_truncated']);
    }

    public function test_non_html_uses_existing_excerpt_and_binary_has_no_description(): void
    {
        $extractor = app(HyperlinkMetadataExtractor::class);
        foreach (['text/plain', 'application/json'] as $type) {
            $result = $extractor->extract('original bytes', $type, 'Normalized excerpt', true);
            $this->assertSame('Normalized excerpt', $result['description']);
            $this->assertSame('normalized_text', $result['description_source']);
            $this->assertTrue($result['description_truncated']);
        }
        $this->assertNull($extractor->extract("\x00\xff", 'application/pdf', null, true)['description']);
    }

    public function test_parser_does_not_load_external_entities_or_resources(): void
    {
        $requests = [];
        libxml_set_external_entity_loader(function (...$arguments) use (&$requests) {
            $requests[] = $arguments;

            return null;
        });
        try {
            app(HyperlinkMetadataExtractor::class)->extract('<!DOCTYPE html SYSTEM "https://example.invalid/evil.dtd" [<!ENTITY ex SYSTEM "file:///etc/passwd">]><html><head><title>&ex;</title></head><body><img src="https://example.invalid/image">Safe</body></html>', 'text/html', null, false);
            $this->assertSame([], $requests);
        } finally {
            libxml_set_external_entity_loader(null);
        }
    }
}
