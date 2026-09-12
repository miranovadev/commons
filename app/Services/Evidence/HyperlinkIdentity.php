<?php

namespace App\Services\Evidence;

use DomainException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

final class HyperlinkIdentity
{
    /** Strict host + path identity; queries, fragments, scheme and port carry no weight. */
    public function resource(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20\x7f\\\\]/', trim($url))) {
            throw new DomainException('A Hyperlink requires an HTTP(S) URL without credentials.');
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        if (! str_starts_with($host, '[')) {
            $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
        }
        $path = $parts['path'] ?? '/';
        $path = preg_replace_callback('/%([a-f0-9]{2})/i', function ($match) {
            $character = chr(hexdec($match[1]));

            return preg_match('/[A-Za-z0-9._~-]/', $character) ? $character : strtoupper($match[0]);
        }, $path);
        $path = UriResolver::removeDotSegments($path);

        return $host.($path === '' ? '/' : $path);
    }

    /** Verify the identity itself, so query-specific responses cannot manufacture versions. */
    public function verificationUrl(string $url): string
    {
        $resource = $this->resource($url);
        $uri = new Uri(trim($url));
        if ($uri->getPort() !== null) {
            throw new DomainException('Hyperlink verification supports standard HTTP(S) ports only.');
        }

        return strtolower($uri->getScheme()).'://'.$resource;
    }
}
