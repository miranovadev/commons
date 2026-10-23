<?php

namespace App\Services\Evidence;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;
use Throwable;

/** Descriptions are source text, never evidence identity or instructions. */
class HyperlinkMetadataExtractor
{
    public const int TITLE_LIMIT = 300;

    public const int DESCRIPTION_LIMIT = 1200;

    public const int LINK_LIMIT = 25;

    public const int LINK_TEXT_LIMIT = 200;

    public const int LINK_URL_LIMIT = 2048;

    public const int LINKS_JSON_LIMIT = 16 * 1024;

    public function extract(string $original, string $type, ?string $excerpt, bool $excerptTruncated, string $effectiveUrl): array
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
        } catch (Throwable) {
            $parsed = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
        }
        $result['description_source'] = $excerpt === null ? null : 'body_text';
        if (! $parsed) {
            return [...$result, ...$this->referenceFailure()];
        }
        $xpath = new DOMXPath($document);
        // Reference failures are supplementary metadata failures, not failed HTTP
        // observations. Publish an atomic empty result, never a partial list.
        try {
            $result = [...$result, ...$this->references($xpath, $effectiveUrl)];
        } catch (Throwable) {
            $result = [...$result, ...$this->referenceFailure()];
        }
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

    protected function references(DOMXPath $xpath, string $effectiveUrl): array
    {
        $base = new Uri($effectiveUrl);
        $seen = [];
        $links = [];
        $bytes = 2; // JSON array brackets; match the cognitive transport's encoding.
        $full = false;
        foreach ($xpath->query('//a[@href][not(ancestor::template or ancestor::script or ancestor::style)]') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#')
                || preg_match('/[\x00-\x20\x7f\\\\]|%(?![a-f0-9]{2})/i', $href)
                || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)
                    && ! preg_match('~^https?://~i', $href))) {
                continue;
            }
            try {
                $url = (string) UriResolver::resolve($base, new Uri($href));
                $uri = new Uri($url);
                if (! in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getUserInfo() !== '') {
                    continue;
                }
                // Validate syntax only. Discovery performs no DNS or retrieval;
                // a future Instrument must independently apply its SSRF rules.
                $host = $uri->getHost();
                $asciiHost = str_starts_with($host, '[') ? $host : idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                if (! $asciiHost || ! filter_var((string) $uri->withHost($asciiHost), FILTER_VALIDATE_URL)) {
                    continue;
                }
            } catch (InvalidArgumentException) {
                continue;
            }
            $key = hash('sha256', $url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($full || count($links) >= self::LINK_LIMIT) {
                $full = true;

                continue;
            }
            // Never shorten a destination into a different resource. Oversized
            // destinations count as observed but are omitted from the collection.
            if (strlen($url) > self::LINK_URL_LIMIT) {
                continue;
            }
            $text = '';
            foreach ($xpath->query('.//text()[not(ancestor::script or ancestor::style or ancestor::template)]', $anchor) as $node) {
                $text .= $node->nodeValue;
            }
            $text = trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $text));
            $link = ['url' => $url, 'text' => mb_substr($text, 0, self::LINK_TEXT_LIMIT)];
            $size = strlen(json_encode($link, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) + ($links === [] ? 0 : 1);
            if ($bytes + $size > self::LINKS_JSON_LIMIT) {
                $full = true;

                continue;
            }
            $links[] = $link;
            $bytes += $size;
        }

        return ['links' => $links, 'links_count' => count($seen), 'links_truncated' => count($seen) > count($links)];
    }

    private function referenceFailure(): array
    {
        return ['links' => [], 'links_count' => null, 'links_truncated' => false, 'links_error' => 'html_reference_extraction_failed'];
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
