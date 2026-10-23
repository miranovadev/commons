<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\VerifiedHyperlink;
use Carbon\CarbonImmutable;
use DomainException;
use Throwable;

class HyperlinkVerifier
{
    public const int MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private HyperlinkIdentity $identity,
        private PublicResourceRetriever $retriever,
        private ArtifactStore $artifacts,
        private HyperlinkMetadataExtractor $descriptions,
    ) {}

    public function verify(string $reference): VerifiedHyperlink
    {
        ['response' => $response, 'url' => $url] = $this->retriever->retrieve(
            $reference, $this->identity->verificationUrl(...), self::MAX_BYTES, 'Hyperlink',
            'text/html, text/plain, application/json, application/pdf',
        );
        $body = $response->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new DomainException('Hyperlink verification requires a non-empty bounded resource.');
        }
        $original = $body;
        $type = strtolower(explode(';', $response->header('Content-Type'))[0]);
        if ($type === 'text/html') {
            $body = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $body);
            $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (str_starts_with($type, 'text/') || $type === 'application/json') {
            $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
        }
        if ($body === '') {
            throw new DomainException('Hyperlink resource contains no verifiable content.');
        }
        $observed = CarbonImmutable::now();
        $modified = null;
        try {
            // Parse only an actual HTTP date; user input and page metadata never set the clock.
            $header = $response->header('Last-Modified');
            if ($header !== '') {
                $parsed = CarbonImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $header, 'UTC');
                if ($parsed && $parsed->format('D, d M Y H:i:s \G\M\T') === $header && $parsed->lte($observed)) {
                    $modified = $parsed;
                }
            }
        } catch (Throwable) {
            // Missing or malformed source timestamps cannot invent version precision.
        }

        $excerpt = str_starts_with($type, 'text/') || $type === 'application/json'
            ? mb_substr(mb_convert_encoding($body, 'UTF-8', 'UTF-8'), 0, 1200) : null;
        $truncated = mb_strlen($body) > 1200;
        // Descriptor normalization must not change the historical fingerprint path above.
        $mediaType = trim($type);
        if (! preg_match('~^[a-z0-9!#$&^_.+\-]+/[a-z0-9!#$&^_.+\-]+$~D', $mediaType)) {
            $mediaType = 'application/octet-stream';
        }
        $artifact = $this->artifacts->preserve($original, $mediaType);
        $description = $this->descriptions->extract($original, $mediaType, $excerpt, $truncated, $url);

        return new VerifiedHyperlink($this->identity->resource($url), hash('sha256', $body), $observed, $modified,
            $excerpt, $truncated, [$artifact], $description);
    }
}
