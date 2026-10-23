<?php

namespace App\Services\Evidence;

use App\Exceptions\ArtifactReadingException;
use DOMDocument;
use DOMNode;

/** One implemented reader family; acquisition provenance never selects a reader. */
final class ArtifactReaderRegistry
{
    public const METHOD = 'commons-native-text';

    public const VERSION = '1';

    public const DELIVERIES = ['inline-text', 'human-text'];

    public function sourceType(string $body, string $declared): string
    {
        if (! mb_check_encoding($body, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $body)
            || preg_match('/^(%PDF-|PK\x03\x04|GIF8)/', $body)) {
            throw new ArtifactReadingException('unsupported_source', 'The preserved bytes are not a supported UTF-8 textual source.');
        }
        $start = ltrim($body, " \n\r\t\xEF\xBB\xBF");
        $mime = strtolower(trim(explode(';', $declared)[0]));
        if (preg_match('/^(?:<!doctype\s+html|<html\b|<(?:head|body|div|p|h[1-6]|article|script)\b)/i', $start)) {
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
            'text/markdown', 'text/x-markdown' => 'markdown',
            'text/x-php', 'application/x-httpd-php' => 'php',
            'text/javascript', 'application/javascript', 'text/x-python', 'text/x-java-source', 'text/css', 'text/x-c', 'text/x-c++' => 'source-code',
            default => 'plain-text',
        };
    }

    public function capabilities(string $sourceType, string $delivery): array
    {
        if (! in_array($delivery, self::DELIVERIES, true)
            || ! in_array($sourceType, ['html', 'plain-text', 'php', 'source-code', 'markdown', 'json', 'xml'], true)) {
            return [];
        }

        return array_map(fn ($type) => [
            'source_type' => $sourceType, 'read_type' => $type,
            'method' => self::METHOD, 'version' => self::VERSION,
            'delivery_methods' => self::DELIVERIES,
            'parameters' => $type === 'text' ? ['offset' => 0, 'max_chars' => (int) config('preparation.default_chars')] : [],
            'max_chars' => $type === 'text' ? (int) config('preparation.max_chars') : null,
            'max_bytes' => (int) config('preparation.selection_bytes'),
        ], $sourceType === 'html' ? ['raw', 'sanitised', 'text'] : ['raw', 'text']);
    }

    public function read(string $body, string $mime, string $type, array $parameters, string $delivery): array
    {
        $source = $this->sourceType($body, $mime);
        if (! in_array($type, array_column($this->capabilities($source, $delivery), 'read_type'), true)) {
            throw new ArtifactReadingException('missing_capability', 'The requested Source Type, Read Type and delivery combination is unsupported.');
        }
        if ($type === 'text') {
            if (array_diff(array_keys($parameters), ['offset', 'max_chars']) !== []
                || ! is_int($parameters['offset'] ?? 0) || ($parameters['offset'] ?? 0) < 0
                || ! is_int($parameters['max_chars'] ?? null) || $parameters['max_chars'] < 1
                || $parameters['max_chars'] > (int) config('preparation.max_chars')) {
                throw new ArtifactReadingException('invalid_parameters', 'Text requires an explicit bounded max_chars and a nonnegative character offset.');
            }
        } elseif ($parameters !== []) {
            throw new ArtifactReadingException('invalid_parameters', 'This whole-resource Read Type accepts no selection parameters.');
        }
        $material = $type === 'sanitised' ? $this->sanitise($body) : $body;
        $length = mb_strlen($material, 'UTF-8');
        $offset = $type === 'text' ? ($parameters['offset'] ?? 0) : 0;
        if ($offset > $length) {
            throw new ArtifactReadingException('invalid_parameters', 'The requested offset is beyond the source character length.');
        }
        $content = $type === 'text' ? mb_substr($material, $offset, $parameters['max_chars'], 'UTF-8') : $material;
        if (strlen($content) > (int) config('preparation.selection_bytes')) {
            throw new ArtifactReadingException('payload_limit', 'The requested representation exceeds the per-selection budget. Request a smaller text selection.');
        }
        $end = $offset + mb_strlen($content, 'UTF-8');

        return ['source_type' => $source, 'read_type' => $type, 'parameters' => $parameters,
            'method' => self::METHOD, 'version' => self::VERSION, 'representation' => $type,
            'delivery_method' => $delivery, 'payload_hash' => 'sha256:'.hash('sha256', $content),
            'payload_bytes' => strlen($content), 'boundaries' => ['unit' => 'unicode-characters',
                'offset' => $offset, 'end' => $end, 'total' => $length, 'has_more' => $end < $length], 'content' => $content];
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
