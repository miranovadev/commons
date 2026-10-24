<?php

namespace App\Services\Evidence;

use App\Exceptions\ArtifactReadingException;
use DOMDocument;
use DOMNode;

/** One implemented reader family; acquisition provenance never selects a reader. */
final class ArtifactReaderRegistry
{
    public const METHOD = 'commons-native-text';

    public const VERSION = '2';

    public function __construct(private readonly PdfTextReader $pdf) {}

    public const DELIVERIES = ['inline-text', 'human-text'];

    public function sourceType(string $body, string $declared): string
    {
        if (strlen($body) > (int) config('preparation.source_bytes')) {
            throw new ArtifactReadingException('source_limit', 'The source exceeds the configured reader limit.');
        }
        if (preg_match('/^%PDF-[12]\.[0-9](?:[\r\n\s]|$)/', $body)) {
            return 'pdf';
        }
        if (! mb_check_encoding($body, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $body)
            || preg_match('/^(%PDF-|PK\x03\x04|GIF8)/', $body)) {
            throw new ArtifactReadingException('unsupported_source', 'The preserved bytes are not a supported UTF-8 textual source.');
        }
        $start = ltrim($body, " \n\r\t\xEF\xBB\xBF");
        $mime = strtolower(trim(explode(';', $declared)[0]));
        if (preg_match('/^(?:<!doctype\s+html|<html\b|<(?:head|body|div|p|h[1-6]|article|script|table|ul|ol)\b)/i', $start)) {
            return 'html';
        }
        if (str_starts_with($start, '<?php') || str_starts_with($start, '<?=')) {
            return 'php';
        }
        if ($mime === 'application/json' || str_ends_with($mime, '+json') || preg_match('/^[\[{]/', $start)) {
            json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return 'json';
            }
            if ($mime === 'application/json' || str_ends_with($mime, '+json')) {
                throw new ArtifactReadingException('invalid_source', 'The declared JSON is malformed.');
            }
        }
        if (str_starts_with($start, '<?xml') || in_array($mime, ['application/xml', 'text/xml'], true) || str_ends_with($mime, '+xml')) {
            // Never resolve an external subset or entities, even for validation.
            if (stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
                throw new ArtifactReadingException('unsupported_source', 'XML with DTD or entity declarations is not supported by this reader.');
            }
            $previous = libxml_use_internal_errors(true);
            try {
                $doc = new DOMDocument;
                if (! $doc->loadXML($body, LIBXML_NONET)) {
                    throw new ArtifactReadingException('invalid_source', 'The declared XML is malformed.');
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            return 'xml';
        }

        return match ($mime) {
            'text/html', 'application/xhtml+xml' => 'html',
            'text/csv', 'application/csv' => 'csv',
            'text/markdown', 'text/x-markdown' => 'markdown',
            'text/x-php', 'application/x-httpd-php' => 'php',
            'text/javascript', 'application/javascript', 'text/x-python', 'text/x-java-source', 'text/css', 'text/x-c', 'text/x-c++' => 'source-code',
            default => 'plain-text',
        };
    }

    public function capabilities(string $sourceType, string $delivery): array
    {
        $types = match ($sourceType) {
            'html' => ['raw', 'sanitised', 'text', 'tables', 'lists'],
            'csv' => ['raw', 'text', 'tables'],
            'pdf' => $this->pdf->version() !== null ? ['text'] : [],
            'plain-text', 'php', 'source-code', 'markdown', 'json', 'xml' => ['raw', 'text'],
            default => [],
        };
        if (! in_array($delivery, self::DELIVERIES, true)) {
            return [];
        }

        return array_map(fn ($type) => [
            'source_type' => $sourceType, 'read_type' => $type,
            'method' => $sourceType === 'pdf' ? PdfTextReader::METHOD : self::METHOD,
            'version' => $sourceType === 'pdf' ? $this->pdf->version() : self::VERSION,
            'delivery_methods' => self::DELIVERIES,
            'parameters' => ['offset' => 0],
            'max_chars' => min(1048576, (int) config('preparation.max_chars')),
            'max_bytes' => min(1048576, (int) config('preparation.selection_bytes')),
            'text_budget' => min(1048576, (int) config('preparation.text_budget')),
        ], $types);
    }

    /** Construct once; content is request-local and never ordinary Receipt metadata. */
    public function represent(string $body, string $mime, string $type, array $parameters, string $delivery): array
    {
        $source = $this->sourceType($body, $mime);
        $capability = array_find($this->capabilities($source, $delivery), fn ($item) => $item['read_type'] === $type);
        if ($capability === null) {
            throw new ArtifactReadingException('missing_capability', 'The requested Source Type, Read Type and delivery combination is unsupported.');
        }
        $this->validateParameters($parameters);
        $material = match (true) {
            $source === 'pdf' => $this->pdf->extract($body),
            $type === 'sanitised' => $this->sanitise($body),
            $type === 'tables' && $source === 'csv' => $this->csvTables($body),
            in_array($type, ['tables', 'lists'], true) => $this->htmlStructure($body, $type),
            default => $body,
        };
        $this->bounded($material);

        return ['source_type' => $source, 'read_type' => $type, 'parameters' => $parameters,
            'method' => $capability['method'], 'version' => $capability['version'], 'representation' => $type,
            'delivery_method' => $delivery, 'content' => $material];
    }

    public function validateParameters(array $parameters): void
    {
        if (array_diff(array_keys($parameters), ['offset', 'max_chars']) !== []
            || ! is_int($parameters['offset'] ?? 0) || ($parameters['offset'] ?? 0) < 0
            || (array_key_exists('max_chars', $parameters) && (! is_int($parameters['max_chars'])
                || $parameters['max_chars'] < 1))) {
            throw new ArtifactReadingException('invalid_parameters', 'Reading accepts an optional nonnegative offset and optional positive max_chars preference.');
        }
    }

    /** Single-reading convenience; multi-selection delivery allocates the shared budget once. */
    public function read(string $body, string $mime, string $type, array $parameters, string $delivery): array
    {
        return app(PreparationAllocation::class)->allocate([$this->represent($body, $mime, $type, $parameters, $delivery)])[0];
    }

    private function bounded(string $material): void
    {
        if (strlen($material) > (int) config('preparation.representation_bytes')) {
            throw new ArtifactReadingException('representation_limit', 'The constructed representation exceeds its processing limit.');
        }
    }

    /** JSON rows retain field boundaries, embedded newlines and formula-like values as data. */
    private function csvTables(string $body): string
    {
        $stream = fopen('php://temp/maxmemory:1048576', 'w+');
        try {
            fwrite($stream, $body);
            rewind($stream);
            $text = '';
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $text .= json_encode(array_map(fn ($value) => $value ?? '', $row), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
                $this->bounded($text);
            }

            return $text;
        } finally {
            fclose($stream);
        }
    }

    private function htmlStructure(string $body, string $type): string
    {
        if ($body === '') {
            return '';
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//script|//style|//template|//iframe|//object|//embed|//noscript|//svg|//math') as $node) {
                $node->parentNode?->removeChild($node);
            }
            $text = '';
            if ($type === 'tables') {
                foreach ($document->getElementsByTagName('table') as $number => $table) {
                    $text .= 'Table '.($number + 1)."\n";
                    foreach ($xpath->query('.//tr', $table) as $row) {
                        // Nested tables are separate tables, not rows in the outer table.
                        $parent = $row->parentNode;
                        while ($parent !== null && $parent->nodeName !== 'table') {
                            $parent = $parent->parentNode;
                        }
                        if ($parent !== $table) {
                            continue;
                        }
                        $cells = [];
                        foreach ($xpath->query('./th|./td', $row) as $cell) {
                            $cells[] = ['text' => trim($this->htmlText($cell)), 'header' => $cell->nodeName === 'th',
                                'rowspan' => max(1, min(1000, (int) $cell->getAttribute('rowspan'))),
                                'colspan' => max(1, min(1000, (int) $cell->getAttribute('colspan')))];
                        }
                        $text .= json_encode($cells, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
                        $this->bounded($text);
                    }
                }
            } else {
                foreach ($xpath->query('//ul[not(ancestor::ul or ancestor::ol)]|//ol[not(ancestor::ul or ancestor::ol)]') as $list) {
                    $this->appendList($list, $text, 0);
                }
            }

            return $text;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function htmlText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if (in_array($child->nodeName, ['ul', 'ol', 'table'], true)) {
                continue;
            }
            $text .= $child->nodeType === XML_TEXT_NODE ? $child->nodeValue : $this->htmlText($child);
            if (in_array($child->nodeName, ['br', 'p', 'div'], true)) {
                $text .= ' ';
            }
        }

        return preg_replace('/\s+/u', ' ', $text);
    }

    private function appendList(DOMNode $list, string &$text, int $depth): void
    {
        if ($depth > 32) {
            throw new ArtifactReadingException('representation_limit', 'HTML list nesting exceeds the supported depth.');
        }
        $number = $list instanceof \DOMElement ? (int) ($list->getAttribute('start') ?: 1) : 1;
        foreach ($list->childNodes as $item) {
            if ($item->nodeName !== 'li') {
                continue;
            }
            if ($item instanceof \DOMElement && $item->hasAttribute('value')) {
                $number = (int) $item->getAttribute('value');
            }
            $text .= str_repeat('  ', $depth).($list->nodeName === 'ol' ? $number++.'. ' : '- ').trim($this->htmlText($item))."\n";
            $this->bounded($text);
            foreach ($item->childNodes as $child) {
                if (in_array($child->nodeName, ['ul', 'ol'], true)) {
                    $this->appendList($child, $text, $depth + 1);
                }
            }
        }
    }

    private function sanitise(string $body): string
    {
        if ($body === '') {
            return '';
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $remove = ['script', 'style', 'head', 'template', 'iframe', 'object', 'embed', 'noscript', 'svg', 'math'];
            $walk = function (DOMNode $node) use (&$walk, $remove): string {
                if ($node->nodeType === XML_TEXT_NODE) {
                    return $node->nodeValue;
                }
                if ($node->nodeType === XML_COMMENT_NODE || in_array(strtolower($node->nodeName), $remove, true)) {
                    return '';
                }
                $text = '';
                foreach ($node->childNodes as $child) {
                    $text .= $walk($child);
                }

                return $text.(in_array(strtolower($node->nodeName), ['p', 'div', 'br', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'section', 'article'], true) ? "\n" : '');
            };

            return trim(preg_replace('/[\t ]+/u', ' ', $walk($document)));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
