<?php

namespace Tests\Feature;

use App\Exceptions\ArtifactReadingException;
use App\Services\Evidence\ArtifactReaderRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArtifactReaderRegistryTest extends TestCase
{
    public static function sources(): array
    {
        return [
            ['<html><p>A &amp; B</p></html>', 'text/html', 'html'],
            ["Hello\r\nworld 💚", 'text/plain', 'plain-text'],
            ['<?php echo shell_exec("do-not-execute");', 'application/octet-stream', 'php'],
            ['# Title\n**Words**', 'text/markdown', 'markdown'],
            ['{"nested":[1,true,null]}', 'application/json', 'json'],
            ['<?xml version="1.0"?><root>text</root>', 'application/xml', 'xml'],
            ['const x = "source";', 'text/javascript', 'source-code'],
            ['<html>Actual HTML</html>', 'image/png', 'html'],
            ['', 'text/plain', 'plain-text'],
        ];
    }

    #[DataProvider('sources')]
    public function test_raw_preserves_every_utf8_byte_and_source_identity(string $body, string $mime, string $type): void
    {
        $result = app(ArtifactReaderRegistry::class)->read($body, $mime, 'raw', [], 'inline-text');
        $this->assertSame($body, $result['content']);
        $this->assertSame($type, $result['source_type']);
        $this->assertSame('sha256:'.hash('sha256', $body), $result['payload_hash']);
        $this->assertSame(strlen($body), $result['payload_bytes']);
        $this->assertFalse($result['boundaries']['has_more']);
    }

    public function test_html_sanitisation_retains_readable_entities_without_scripts_or_external_content(): void
    {
        $body = '<!doctype html><html><head><title>Head</title><style>bad-style</style></head><body><h1>Title</h1><script>bad-script</script><p>A &amp; B&nbsp;💚</p><iframe src="https://example.test">bad-frame</iframe><p>Unclosed';
        $result = app(ArtifactReaderRegistry::class)->read($body, 'text/html', 'sanitised', [], 'human-text');
        $this->assertStringContainsString("Title\nA & B", $result['content']);
        $this->assertStringContainsString('Unclosed', $result['content']);
        $this->assertStringNotContainsString('bad-', $result['content']);
        $this->assertStringNotContainsString('<', $result['content']);
        $this->assertSame($body, app(ArtifactReaderRegistry::class)->read($body, 'text/html', 'raw', [], 'human-text')['content']);
    }

    public function test_text_boundaries_are_unicode_character_offsets_and_explicit_selections(): void
    {
        $reader = app(ArtifactReaderRegistry::class);
        $selected = $reader->read('A💚éZ', 'text/plain', 'text', ['offset' => 1, 'max_chars' => 2], 'inline-text');
        $this->assertSame('💚é', $selected['content']);
        $this->assertSame(['unit' => 'unicode-characters', 'offset' => 1, 'end' => 3, 'total' => 4, 'has_more' => true], $selected['boundaries']);
        $empty = $reader->read('', 'text/plain', 'text', ['max_chars' => 10], 'human-text');
        $this->assertSame('', $empty['content']);
        $this->assertSame(0, $empty['boundaries']['end']);
    }

    public static function invalidReads(): array
    {
        return [
            ["\xFF\x00", 'text/plain', 'raw', [], 'unsupported_source'],
            ['%PDF-1.4 fake', 'text/plain', 'raw', [], 'unsupported_source'],
            ['{invalid}', 'application/json', 'raw', [], 'invalid_source'],
            ['<root>', 'application/xml', 'raw', [], 'invalid_source'],
            ['<!DOCTYPE root [<!ENTITY x SYSTEM "file:///etc/passwd">]><root>&x;</root>', 'application/xml', 'raw', [], 'unsupported_source'],
            ['plain', 'text/plain', 'sanitised', [], 'missing_capability'],
            ['<html>text</html>', 'text/html', 'regex', [], 'missing_capability'],
            ['text', 'text/plain', 'raw', ['max_chars' => 2], 'invalid_parameters'],
            ['text', 'text/plain', 'text', [], 'invalid_parameters'],
            ['text', 'text/plain', 'text', ['offset' => 10, 'max_chars' => 2], 'invalid_parameters'],
            ['text', 'text/plain', 'text', ['offset' => -1, 'max_chars' => 2], 'invalid_parameters'],
            [str_repeat('x', 16385), 'text/plain', 'raw', [], 'payload_limit'],
        ];
    }

    #[DataProvider('invalidReads')]
    public function test_invalid_or_unsupported_reading_is_explicit(string $body, string $mime, string $type, array $parameters, string $failure): void
    {
        try {
            app(ArtifactReaderRegistry::class)->read($body, $mime, $type, $parameters, 'inline-text');
            $this->fail('Invalid reading was accepted.');
        } catch (ArtifactReadingException $exception) {
            $this->assertSame($failure, $exception->classification);
        }
    }

    public function test_capabilities_advertise_only_implemented_combinations_and_delivery_methods(): void
    {
        $registry = app(ArtifactReaderRegistry::class);
        $this->assertSame(['raw', 'sanitised', 'text'], array_column($registry->capabilities('html', 'inline-text'), 'read_type'));
        $this->assertSame([], $registry->capabilities('pdf', 'inline-text'));
        $this->assertSame([], $registry->capabilities('html', 'native-attachment'));
    }
}
