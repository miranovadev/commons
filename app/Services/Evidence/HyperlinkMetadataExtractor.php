<?php

namespace App\Services\Evidence;

use DOMDocument;
use DOMXPath;

/** Descriptions are source text, never evidence identity or instructions. */
class HyperlinkMetadataExtractor
{
    public const int TITLE_LIMIT = 300;

    public const int DESCRIPTION_LIMIT = 1200;

    public function extract(string $original, string $type, ?string $excerpt, bool $excerptTruncated): array
    {
        $result = ['title' => null, 'title_source' => null, 'description' => $excerpt,
            'description_source' => $excerpt === null ? null : 'normalized_text',
            'description_truncated' => $excerpt !== null && $excerptTruncated];
        if ($type !== 'text/html') {
            return $result;
        }

        // Input has already passed the verifier's 2 MiB bound. No DTD loading,
        // entity substitution, XInclude or resource fetching is enabled.
        $document = new DOMDocument;
        $errors = libxml_use_internal_errors(true);
        try {
            $parsed = $document->loadHTML('<?xml encoding="UTF-8">'.mb_convert_encoding($original, 'UTF-8', 'UTF-8'), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
        }
        $result['description_source'] = $excerpt === null ? null : 'body_text';
        if (! $parsed) {
            return $result;
        }
        $xpath = new DOMXPath($document);
        $lower = "'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'";
        foreach ([
            'html_title' => '//title',
            'og_title' => "//meta[translate(@property, $lower)='og:title']/@content",
            'h1' => '//h1',
        ] as $source => $query) {
            if (($value = $this->first($xpath, $query)) !== null) {
                $result['title'] = mb_substr($value, 0, self::TITLE_LIMIT);
                $result['title_source'] = $source;
                break;
            }
        }
        foreach ([
            'meta_description' => "//meta[translate(@name, $lower)='description']/@content",
            'og_description' => "//meta[translate(@property, $lower)='og:description']/@content",
        ] as $source => $query) {
            if (($value = $this->first($xpath, $query)) !== null) {
                return [...$result, 'description' => mb_substr($value, 0, self::DESCRIPTION_LIMIT),
                    'description_source' => $source, 'description_truncated' => mb_strlen($value) > self::DESCRIPTION_LIMIT];
            }
        }
        // Body fallback excludes head metadata and executable/styling text.
        foreach (iterator_to_array($xpath->query('//script | //style')) as $node) {
            $node->parentNode?->removeChild($node);
        }
        $body = $this->first($xpath, '//body');
        $body = $body === null ? null : trim(preg_replace('/\s+/u', ' ', $body));

        return [...$result, 'description' => $body === null || $body === '' ? null : mb_substr($body, 0, self::DESCRIPTION_LIMIT),
            'description_source' => $body === null || $body === '' ? null : 'body_text',
            'description_truncated' => $body !== null && mb_strlen($body) > self::DESCRIPTION_LIMIT];
    }

    private function first(DOMXPath $xpath, string $query): ?string
    {
        foreach ($xpath->query($query) as $node) {
            // DOM has decoded entities once. Decoding twice would change literal wording.
            $value = preg_replace('/^[\s\x{00a0}]+|[\s\x{00a0}]+$/u', '', $node->textContent);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
