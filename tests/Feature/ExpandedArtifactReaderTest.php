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
        $hit = $this->read($html, 'text/html', 'regex', ['pattern' => 'contract.{0,30}engineering']);
        $this->assertStringContainsString('[1] Source characters: [7, 29)', $hit['content']);
        $this->assertStringContainsString('Match: "contract & engineering"', $hit['content']);
        $this->assertStringNotContainsString('secret', $hit['content']);
        $this->assertNotSame($text['representation_hash'], $hit['representation_hash']);
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
    public function test_historical_v3_first_regex_window(string $pattern, int $offset, int $maximum, ?array $span, int $start, string $expected): void
    {
        $body = "é💚猫e\u{301}éZ";
        $parameters = ['pattern' => $pattern, 'offset' => $offset, 'max_chars' => $maximum];
        $representation = app(ArtifactReaderRegistry::class)->represent($body, 'text/plain', 'regex', $parameters, 'human-text', '3');
        $result = app(PreparationAllocation::class)->allocate([$representation])[0];
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
            $report = $reader->represent(str_repeat($literal, 20), 'text/plain', 'regex', ['pattern' => $literal.'+'], 'human-text');
            $report['parameters']['offset'] = mb_strpos($report['content'], 'Match: "'.$literal) + mb_strlen('Match: "');
            $selections[] = $report;
        }
        $reads = app(PreparationAllocation::class)->allocate($selections, 90000);
        $this->assertSame(['💚💚', 'éé', 'x'], array_column($reads, 'content'));
        $this->assertSame(13, array_sum(array_column($reads, 'payload_bytes')));
    }

    public function test_ordered_results_preserve_unicode_source_coordinates_and_overlapping_context(): void
    {
        $body = "é💚猫e\u{301}猫💚";
        $result = $this->read($body, 'text/plain', 'regex', ['pattern' => '猫', 'context_chars' => 3]);
        $expected = "Pattern: \"猫\"\nTotal matches: 2\nIncluded matches: 2\n"
            ."\n[1] Source characters: [2, 3)\nBefore: \"é💚\"\nMatch: \"猫\"\nAfter: \"e\u{301}猫\"\n"
            ."\n[2] Source characters: [5, 6)\nBefore: \"猫e\u{301}\"\nMatch: \"猫\"\nAfter: \"💚\"\n";
        $this->assertSame($expected, $result['content']);
        $this->assertSame('4', $result['version']);
        $this->assertSame('regex-results', $result['representation']);
        $this->assertSame(mb_strlen($expected), $result['boundaries']['total']);
        $this->assertSame('sha256:'.hash('sha256', $expected), $result['representation_hash']);
        $limited = $this->read($body, 'text/plain', 'regex', ['pattern' => '猫', 'limit' => 1, 'context_chars' => 0]);
        $this->assertSame(['total_matches' => 2, 'included_matches' => 1, 'byte_limited' => false], $limited['result_set']);
        $this->assertStringContainsString("Before: \"\"\nMatch: \"猫\"\nAfter: \"\"", $limited['content']);
        $this->assertStringNotContainsString('[2] Source', $limited['content']);
    }

    public function test_successive_windows_reassemble_a_result_set_larger_than_the_source(): void
    {
        $reader = app(ArtifactReaderRegistry::class);
        $body = str_repeat('💚a', 260);
        $query = ['pattern' => 'a', 'context_chars' => 2];
        $full = $reader->represent($body, 'text/plain', 'regex', $query, 'inline-text')['content'];
        $this->assertGreaterThan(12000, mb_strlen($full));
        $joined = '';
        $offset = 0;
        do {
            $page = $reader->read($body, 'text/plain', 'regex', $query + ['offset' => $offset, 'max_chars' => 3000], 'inline-text');
            $this->assertSame($offset, $page['boundaries']['offset']);
            $this->assertSame(mb_substr($full, $offset, 3000), $page['content']);
            $this->assertSame('sha256:'.hash('sha256', $full), $page['representation_hash']);
            $this->assertSame('sha256:'.hash('sha256', $page['content']), $page['payload_hash']);
            $joined .= $page['content'];
            $offset = $page['boundaries']['end'];
        } while ($page['boundaries']['has_more']);
        $this->assertSame($full, $joined);
    }

    public function test_zero_results_and_zero_width_global_matches_are_distinct(): void
    {
        $none = $this->read('💚', 'text/plain', 'regex', ['pattern' => 'missing']);
        $this->assertSame("Pattern: \"missing\"\nTotal matches: 0\nIncluded matches: 0\n", $none['content']);
        $zero = $this->read('💚', 'text/plain', 'regex', ['pattern' => '^|.|$']);
        $this->assertSame(3, $zero['result_set']['total_matches']);
        foreach (['[1] Source characters: [0, 0)', '[2] Source characters: [0, 1)', '[3] Source characters: [1, 1)'] as $line) {
            $this->assertStringContainsString($line, $zero['content']);
        }
    }

    public function test_match_and_result_byte_ceilings_never_claim_incomplete_output_is_complete(): void
    {
        $cap = \App\Services\Evidence\RegexSearch::MAX_MATCHES;
        $exact = $this->read(str_repeat('a', $cap), 'text/plain', 'regex', ['pattern' => 'a', 'limit' => 1]);
        $this->assertSame($cap, $exact['result_set']['total_matches']);
        $many = $this->read(str_repeat('a', $cap + 1), 'text/plain', 'regex', ['pattern' => 'a', 'limit' => 2]);
        $this->assertSame(['total_matches' => null, 'included_matches' => 2, 'byte_limited' => false], $many['result_set']);
        $this->assertStringContainsString('enumeration ceiling reached', $many['content']);
        $all = $this->read(str_repeat('a', $cap + 1), 'text/plain', 'regex', ['pattern' => 'a', 'context_chars' => 0]);
        $this->assertSame(['total_matches' => null, 'included_matches' => $cap, 'byte_limited' => false], $all['result_set']);
        config(['preparation.representation_bytes' => 700]);
        $long = $this->read(str_repeat('a', 600), 'text/plain', 'regex', ['pattern' => 'a{300}', 'context_chars' => 0]);
        $this->assertSame(['total_matches' => 2, 'included_matches' => 1, 'byte_limited' => true], $long['result_set']);
        $this->assertStringContainsString('result byte ceiling reached', $long['content']);
        $this->assertStringNotContainsString('[2] Source', $long['content']);
        $this->assertLessThanOrEqual(700, strlen($long['content']));
    }

    public function test_a_complete_report_that_fits_the_exact_representation_ceiling_is_retained(): void
    {
        $query = ['pattern' => 'a', 'context_chars' => 0];
        $full = $this->read('a', 'text/plain', 'regex', $query);
        config(['preparation.representation_bytes' => strlen($full['content'])]);
        $this->assertSame($full, $this->read('a', 'text/plain', 'regex', $query));
        config(['preparation.representation_bytes' => strlen($full['content']) - 1]);
        $short = $this->read('a', 'text/plain', 'regex', $query);
        $this->assertSame(['total_matches' => 1, 'included_matches' => 0, 'byte_limited' => true], $short['result_set']);
    }

    public function test_pdf_result_version_tracks_poppler_and_preserves_v3_first_match_identity(): void
    {
        $body = TextPdf::make(['Café research', 'More research']);
        $reader = app(ArtifactReaderRegistry::class);
        $parameters = ['pattern' => 'research'];
        $current = $reader->read($body, 'application/pdf', 'regex', $parameters, 'human-text');
        $legacy = $reader->represent($body, 'application/pdf', 'regex', $parameters, 'human-text', '3');
        $this->assertSame(2, $current['result_set']['total_matches']);
        $this->assertSame(PdfTextReader::METHOD, $current['method']);
        $this->assertStringEndsWith('/regex-2', $current['version']);
        $this->assertStringEndsWith('/regex-1', $legacy['version']);
        $this->assertSame('regex', $legacy['representation']);
    }

    public static function invalidResultParameters(): array
    {
        return [[['offset' => -1]], [['limit' => 0]], [['limit' => 1.5]], [['context_chars' => -1]], [['context_chars' => 513]]];
    }

    #[DataProvider('invalidResultParameters')]
    public function test_invalid_result_parameters_are_rejected(array $parameters): void
    {
        $this->expectException(ArtifactReadingException::class);
        $this->read('abc', 'text/plain', 'regex', ['pattern' => '.'] + $parameters);
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
