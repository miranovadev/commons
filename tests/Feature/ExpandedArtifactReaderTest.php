<?php

namespace Tests\Feature;

use App\Exceptions\ArtifactReadingException;
use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\Evidence\PdfTextReader;
use App\Services\Evidence\PreparationAllocation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\TextPdf;
use Tests\TestCase;

class ExpandedArtifactReaderTest extends TestCase
{
    private function read(string $body, string $mime, string $type, array $parameters = []): array
    {
        return app(ArtifactReaderRegistry::class)->read($body, $mime, $type, $parameters, 'inline-text');
    }

    public function test_html_text_retains_visible_words_and_boundaries_and_regex_uses_the_same_representation(): void
    {
        $html = '<html><head><title>metadata</title><style>secret</style></head><body><h5>Résumé</h5>'
            .'<p>con<em>tract</em> &amp; <b>engineering</b></p><ul><li>First<li>第二</ul><p>One<br>Two</p><p><img alt="Diagram"> <svg><text>visible label</text></svg></p>'
            .'<table><tr><td>A<td>B</table><pre>  indented'."\n".'    code</pre>'
            .'<template>secret</template><span hidden>secret</span><script>secret</script></body></html>';
        $text = $this->read($html, 'text/html', 'text');
        $this->assertSame("Résumé\ncontract & engineering\nFirst\n第二\nOne\nTwo\nDiagram visible label\nA\nB\n  indented\n    code", $text['content']);
        $this->assertSame($html, $this->read($html, 'text/html', 'raw')['content']);
        $hit = $this->read($html, 'text/html', 'regex', ['pattern' => 'contract.{0,30}engineering', 'max_chars' => 8]);
        $this->assertSame('contract', $hit['content']);
        $this->assertSame(['start' => 7, 'end' => 29], $hit['boundaries']['match']);
        $this->assertSame($text['representation_hash'], $hit['representation_hash']);
        $this->assertSame('3', $text['version']);
    }

    public static function regexWindows(): array
    {
        // Two occurrences of é, one combining mark, one supplementary character.
        return [
            'first occurrence' => ['é', 0, 1, [0, 1], 0, 'é'],
            'middle with preceding context' => ['猫', -1, 3, [2, 3], 1, '💚猫e'],
            'left clamp' => ['猫', PHP_INT_MIN, 2, [2, 3], 0, 'é💚'],
            'right clamp' => ['猫', PHP_INT_MAX, 2, [2, 3], 7, ''],
            'case-insensitive end' => ['(?i)z$', 0, 9, [6, 7], 6, 'Z'],
            'zero-width EOF' => ['$', 0, 2, [7, 7], 7, ''],
            'no match' => ['absent', -100, 2, null, 7, ''],
        ];
    }

    #[DataProvider('regexWindows')]
    public function test_first_regex_window(string $pattern, int $offset, int $maximum, ?array $span, int $start, string $expected): void
    {
        $body = "é💚猫e\u{301}éZ";
        $parameters = ['pattern' => $pattern, 'offset' => $offset, 'max_chars' => $maximum];
        $result = $this->read($body, 'text/plain', 'regex', $parameters);
        $bounds = $result['boundaries'];
        $this->assertSame($parameters, $result['parameters']);
        $this->assertArrayHasKey('match', $bounds);
        $this->assertSame($span === null ? null : ['start' => $span[0], 'end' => $span[1]], $bounds['match']);
        $this->assertSame($expected, $result['content']);
        $this->assertSame($start, $bounds['offset']);
        $this->assertSame($start + mb_strlen($expected), $bounds['end']);
        $this->assertSame(7, $bounds['total']);
        $this->assertSame('sha256:'.hash('sha256', $expected), $result['payload_hash']);
    }

    public function test_regex_readings_with_a_large_character_allowance_still_share_the_byte_ceiling(): void
    {
        config(['preparation.selection_bytes' => 10, 'preparation.delivery_bytes' => 13]);
        $reader = app(ArtifactReaderRegistry::class);
        $selections = [];
        foreach (['💚', 'é', 'x'] as $literal) {
            $selections[] = $reader->represent(str_repeat($literal, 20), 'text/plain', 'regex', ['pattern' => $literal], 'human-text');
        }
        $reads = app(PreparationAllocation::class)->allocate($selections, 90000);
        $this->assertSame(['💚💚', 'éé', 'x'], array_column($reads, 'content'));
        $this->assertSame(13, array_sum(array_column($reads, 'payload_bytes')));
    }

    public static function invalidRegex(): array
    {
        return [[null], [''], ['('], [str_repeat('x', 513)], ["\xff"], ["bad\0pattern"], ['(?x)abc'],
            ['(?R)'], ['(*LIMIT_MATCH=123)x'], ['\\C'], ['(a+)+$']];
    }

    #[DataProvider('invalidRegex')]
    public function test_invalid_or_excessive_regex_is_distinct_from_no_match(?string $pattern): void
    {
        try {
            $this->read(str_repeat('a', 5000).'!', 'text/plain', 'regex', ['pattern' => $pattern]);
            $this->fail('Invalid or excessive pattern was accepted.');
        } catch (ArtifactReadingException $e) {
            $this->assertSame('invalid_parameters', $e->classification);
        }
    }

    public function test_pdf_text_pages_unicode_and_representation_relative_ranges_preserve_source(): void
    {
        $body = TextPdf::make(['Café research €', 'Second page']);
        $before = hash('sha256', $body);
        $result = $this->read($body, 'application/octet-stream', 'text');
        $this->assertSame('pdf', $result['source_type']);
        $this->assertSame(PdfTextReader::METHOD, $result['method']);
        $this->assertMatchesRegularExpression('/^1\/poppler-[\d.]+$/', $result['version']);
        $this->assertStringContainsString('Café research €', $result['content']);
        $this->assertStringContainsString('Second page', $result['content']);
        $part = $this->read($body, 'application/pdf', 'text', ['offset' => 2, 'max_chars' => 6]);
        $this->assertSame(mb_substr($result['content'], 2, 6), $part['content']);
        $this->assertSame($result['representation_hash'], $part['representation_hash']);
        $this->assertSame($before, hash('sha256', $body));
    }

    public static function pdfFailures(): array
    {
        return [['malformed', 'reader_failure'], ['image-only', 'no_extractable_text'], ['output', 'representation_limit']];
    }

    #[DataProvider('pdfFailures')]
    public function test_pdf_failures_do_not_claim_successful_reading(string $case, string $expected): void
    {
        $body = match ($case) {
            'malformed' => "%PDF-1.4\nnot a PDF",
            'image-only' => TextPdf::make([null]),
            default => TextPdf::make([str_repeat('x', 5000)]),
        };
        if ($case === 'output') {
            config(['preparation.pdf.output_bytes' => 20]);
        }
        try {
            $this->read($body, 'application/pdf', 'text');
            $this->fail('A failed extraction was delivered.');
        } catch (ArtifactReadingException $exception) {
            $this->assertSame($expected, $exception->classification);
        }
    }

    public function test_pdf_actions_and_external_uris_are_not_executed_or_fetched(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);
        try {
            $pdf = TextPdf::make(['Only preserved text'], '/OpenAction << /S /URI /URI (http://'.$address.'/not-requested) >>');
            $this->assertStringContainsString('Only preserved text', $this->read($pdf, 'application/pdf', 'text')['content']);
            $read = [$server];
            $write = $except = [];
            $this->assertSame(0, stream_select($read, $write, $except, 0, 10000));
            $js = TextPdf::make(['Text with an inert action'], '/OpenAction << /S /JavaScript /JS (throw new Error("not executed")) >>');
            $this->assertStringContainsString('Text with an inert action', $this->read($js, 'application/pdf', 'text')['content']);
        } finally {
            fclose($server);
        }
    }

    public function test_pdf_subprocess_timeout_memory_cpu_and_write_limits(): void
    {
        $binary = tempnam(sys_get_temp_dir(), 'commons-reader-test-');
        try {
            // Trusted executable fixture checks the same launcher used for Poppler.
            file_put_contents($binary, "#!/usr/bin/python3\nimport sys, resource, time\nif '-v' in sys.argv:\n print('pdftotext version 99.0'); sys.exit(0)\nassert resource.getrlimit(resource.RLIMIT_AS)[0] == 268435456\nassert resource.getrlimit(resource.RLIMIT_FSIZE)[0] == 0\nassert resource.getrlimit(resource.RLIMIT_CPU)[0] == 1\ntime.sleep(5)\n");
            chmod($binary, 0700);
            config(['preparation.pdf.binary' => $binary, 'preparation.pdf.timeout_seconds' => 1]);
            $started = microtime(true);
            try {
                $this->read(TextPdf::make(), 'application/pdf', 'text');
                $this->fail('Timed-out reading succeeded.');
            } catch (ArtifactReadingException $exception) {
                $this->assertSame('reader_timeout', $exception->classification);
                $this->assertLessThan(3, microtime(true) - $started);
            }
        } finally {
            unlink($binary);
        }
    }

    public function test_runaway_pdf_output_is_stopped_and_private_temporary_input_is_removed(): void
    {
        $binary = tempnam(sys_get_temp_dir(), 'commons-reader-test-');
        $before = glob(sys_get_temp_dir().'/commons-pdf-*');
        try {
            file_put_contents($binary, "#!/usr/bin/python3\nimport sys\nif '-v' in sys.argv:\n print('pdftotext version 99.0'); sys.exit(0)\nwhile True:\n sys.stdout.write('x' * 8192); sys.stdout.flush()\n");
            chmod($binary, 0700);
            config(['preparation.pdf.binary' => $binary, 'preparation.pdf.output_bytes' => 100]);
            $started = microtime(true);
            try {
                $this->read(TextPdf::make(), 'application/pdf', 'text');
                $this->fail('Unbounded reader output succeeded.');
            } catch (ArtifactReadingException $exception) {
                $this->assertSame('representation_limit', $exception->classification);
                $this->assertLessThan(3, microtime(true) - $started);
                $this->assertSame($before, glob(sys_get_temp_dir().'/commons-pdf-*'));
            }
        } finally {
            unlink($binary);
        }
    }

    public function test_html_tables_retain_rows_cells_spans_headers_entities_and_nested_markup(): void
    {
        $body = '<table><tr><th>Name</th><th colspan="2">Values</th></tr><tr><td>A <strong>&amp; B</strong></td><td></td><td>💚</td></tr><tr></tr></table><table><tr><td>Second<script>bad()</script></td></tr></table>';
        $text = $this->read($body, 'text/plain', 'tables')['content'];
        $lines = explode("\n", $text);
        $this->assertSame('Table 1', $lines[0]);
        $header = json_decode($lines[1], true);
        $this->assertTrue($header[0]['header']);
        $this->assertSame(2, $header[1]['colspan']);
        $this->assertSame(['A & B', '', '💚'], array_column(json_decode($lines[2], true), 'text'));
        $this->assertSame([], json_decode($lines[3], true));
        $this->assertStringContainsString('Table 2', $text);
        $this->assertStringNotContainsString('bad()', $text);
        $this->assertSame($body, $this->read($body, 'text/html', 'raw')['content']);
    }

    public function test_nested_tables_are_separate_and_malformed_html_remains_readable(): void
    {
        $text = $this->read('<table><tr><td>Outer<table><tr><td>Inner</table><td>Tail', 'text/html', 'tables')['content'];
        $this->assertSame(1, substr_count($text, 'Inner'));
        $this->assertSame(1, substr_count($text, 'Outer'));
        $this->assertStringContainsString('Table 2', $text);
    }

    public function test_ordered_and_unordered_lists_preserve_nesting_and_entities(): void
    {
        $body = '<ol start="3"><li>One <b>&amp; two</b><ul><li>Nested 💚</li></ul></li><li value="8">Eight</li></ol><ul><li>Last<script>bad()</script></li></ul>';
        $text = $this->read($body, 'text/html', 'lists')['content'];
        $this->assertSame("3. One & two\n  - Nested 💚\n8. Eight\n- Last\n", $text);
        $part = $this->read($body, 'text/html', 'lists', ['offset' => 3, 'max_chars' => 7]);
        $this->assertSame(mb_substr($text, 3, 7), $part['content']);
        $this->assertTrue($part['boundaries']['has_more']);
    }

    public function test_csv_tables_keep_quoting_embedded_newlines_empty_cells_and_formulas_as_data(): void
    {
        $body = "name,value,empty\r\n\"A, B\",\"line one\nline two\",\r\n\"say \"\"hi\"\"\",=SUM(A1),💚\r\n";
        $result = $this->read($body, 'text/csv', 'tables');
        $rows = array_map(fn ($line) => json_decode($line, true), explode("\n", rtrim($result['content'], "\n")));
        $this->assertSame([['name', 'value', 'empty'], ['A, B', "line one\nline two", ''], ['say "hi"', '=SUM(A1)', '💚']], $rows);
        $this->assertSame('csv', $result['source_type']);
        $this->assertSame($body, $this->read($body, 'text/csv', 'raw')['content']);
    }

    public function test_large_csv_tables_and_html_use_the_shared_allocation_budget(): void
    {
        config(['preparation.text_budget' => 41]);
        $result = $this->read(str_repeat("alpha,beta\n", 5000), 'text/csv', 'tables');
        $this->assertSame(41, $result['boundaries']['length']);
        $this->assertTrue($result['boundaries']['has_more']);
        $this->assertGreaterThan(50000, $result['boundaries']['total']);
        $this->assertSame('', $this->read('', 'text/csv', 'tables')['content']);
    }

    public function test_mime_cannot_make_binary_content_text_or_hide_a_recognised_format(): void
    {
        $this->assertSame('pdf', $this->read(TextPdf::make(), 'text/csv', 'text')['source_type']);
        $this->assertSame('html', $this->read('<table><tr><td>Actual HTML</td></tr></table>', 'application/pdf', 'tables')['source_type']);
        $this->expectException(ArtifactReadingException::class);
        $this->read("PK\x03\x04\0binary", 'text/csv', 'tables');
    }
}
