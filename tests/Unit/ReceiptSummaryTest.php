<?php

namespace Tests\Unit;

use App\Models\Receipt;
use App\Services\Evidence\ReceiptSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReceiptSummaryTest extends TestCase
{
    public static function types(): array
    {
        return [
            'hyperlink' => ['hyperlink', ['title' => 'The Matrix', 'description' => 'An exploratory framework', 'content_verified' => true], 'title', 'The Matrix'],
            'web search' => ['web_search', ['observation' => ['query' => 'emergence', 'results' => [[], []], 'provider' => 'fixture']], 'result_count', 2],
            'empty search' => ['web_search', ['observation' => ['query' => 'nothing', 'results' => [], 'provider' => 'fixture']], 'result_count', 0],
            'proposition search' => ['proposition_search', ['proposition_collection' => ['query' => 'commons', 'results' => [[]], 'domain' => 'emergence']], 'query', 'commons'],
            'citation' => ['citation', [], 'target_id', 'https://example.test/record'],
            'preparation' => ['preparation', ['purpose' => 'Read the source', 'outcome' => ['type' => 'contributed'], 'content_verified' => true], 'outcome', 'contributed'],
            'failed preparation' => ['preparation', ['outcome' => ['type' => 'failed'], 'content_verified' => false, 'failure' => ['classification' => 'storage_failure']], 'content_verified', false],
            'load file' => ['load_file', ['method' => 'download', 'filename' => 'source.pdf', 'artifacts' => [['hash' => 'sha256:abc', 'size' => 42, 'media_type' => 'application/pdf', 'path' => '/private/file']]], 'filename', 'source.pdf'],
            'tragedy' => ['tragedy', ['tragedy_id' => 'catastrophe', 'governing_committal_id' => 'governor'], 'tragedy_id', 'catastrophe'],
            'failed hyperlink' => ['hyperlink', ['verification_error' => '/private/diagnostic'], 'outcome', 'failed'],
            'failed search' => ['web_search', ['observation_error' => 'Bearer secret'], 'outcome', 'failed'],
            'failed download' => ['load_file', ['source_url' => 'https://example.test/source', 'observation_error' => 'secret diagnostic'], 'outcome', 'failed'],
        ];
    }

    #[DataProvider('types')]
    public function test_every_supported_type_has_a_bounded_description(string $type, array $metadata, string $key, mixed $expected): void
    {
        $summary = $this->summary($type, $metadata);
        $this->assertSame($expected, $summary[$key]);
        $this->assertLessThanOrEqual(ReceiptSummary::JSON_LIMIT, strlen(json_encode($summary)));
        $this->assertStringNotContainsString('/private', json_encode($summary));
        $this->assertStringNotContainsString('Bearer', json_encode($summary));
    }

    public function test_preparation_preserves_recorded_read_boundaries_without_source_bytes(): void
    {
        $summary = $this->summary('preparation', ['purpose' => 'Inspect source',
            'selections' => [['artifact_id' => 'sha256:abc', 'read_type' => 'sanitised', 'parameters' => ['offset' => 7, 'max_chars' => 10]]],
            'deliveries' => [['artifact_id' => 'sha256:abc', 'read_type' => 'sanitised', 'payload' => 'SECRET BODY',
                'boundaries' => ['offset' => 7, 'length' => 10, 'total' => 30, 'has_more' => true]]],
            'outcome' => ['type' => 'deferred', 'reason' => 'No contribution yet'], 'content_verified' => true]);
        $this->assertSame(['artifact_id' => 'sha256:abc', 'has_more' => true, 'length' => 10,
            'offset' => 7, 'read_type' => 'sanitised', 'total' => 30], $summary['deliveries'][0]);
        $this->assertSame(7, $summary['selections'][0]['offset']);
        $this->assertSame('deferred', $summary['outcome']);
        $this->assertStringNotContainsString('SECRET BODY', json_encode($summary));
    }

    public function test_fallback_uses_first_three_eligible_fields_deterministically_and_bounds_nested_data(): void
    {
        $metadata = ['zulu' => 'not selected', 'token' => 'secret', 'charlie' => false,
            'bravo' => ['a' => ['a' => ['a' => 'too deep']], 'b' => array_fill(0, 100, 'value'), 'body' => 'SECRET', 'c' => 0, 'd' => 'ignored'],
            'alpha' => str_repeat('界', 10000)];
        $summary = $this->summary('future_type', $metadata);
        $this->assertSame(['alpha', 'bravo', 'charlie'], array_keys($summary));
        $this->assertSame($summary, $this->summary('future_type', array_reverse($metadata, true)));
        $this->assertSame(ReceiptSummary::STRING_LIMIT, mb_strlen($summary['alpha']));
        $this->assertSame(['value', 'value', 'value'], $summary['bravo']['b']);
        $this->assertStringNotContainsString('SECRET', json_encode($summary));
        $this->assertStringNotContainsString('too deep', json_encode($summary));
    }

    public function test_sensitive_and_unbounded_metadata_cannot_become_a_summary(): void
    {
        $metadata = ['api_key' => 'SECRET', 'credentials' => ['user' => 'SECRET'], 'body' => str_repeat('BODY', 100000),
            'private_storage_path' => '/private/file', 'diagnostic' => 'stack', 'authorization' => 'Basic SECRET',
            'a' => '/workspace/private/file', 'b' => 'Bearer SECRET', 'c' => 'password=SECRET'];
        $this->assertSame(['observation_type' => 'unknown'], $this->summary('unknown', $metadata));
        $summary = $this->summary('hyperlink', [...$metadata, 'description' => str_repeat('界', 100000), 'title' => 'Recognisable title']);
        $this->assertSame('Recognisable title', $summary['title']);
        $this->assertArrayNotHasKey('body', $summary);
        $this->assertLessThanOrEqual(ReceiptSummary::JSON_LIMIT, strlen(json_encode($summary)));
    }

    public function test_fallback_excludes_storage_locations_even_under_innocuous_keys(): void
    {
        foreach (['/mnt/artifacts/private.txt', 'Read /opt/app/source.txt', 'C:/private/source.txt',
            'C:\\private\\source.txt', '\\\\server\\private\\source.txt', 'file:///mnt/private.txt',
            's3://private-bucket/source.txt', 'gs://private-bucket/source.txt',
            'abfss://private-container/source.txt'] as $location) {
            $this->assertSame(['label' => 'Recorded observation'], $this->summary('future_type', [
                'detail' => $location, 'label' => 'Recorded observation',
            ]));
        }
        $this->assertSame(['detail' => 'https://example.test/source'], $this->summary('future_type', [
            'detail' => 'https://example.test/source',
        ]));
    }

    private function summary(string $type, array $metadata): array
    {
        return (new ReceiptSummary)->forReceipt((new Receipt)->forceFill([
            'type' => $type, 'reference' => 'https://example.test/record', 'metadata' => $metadata,
        ]));
    }
}
