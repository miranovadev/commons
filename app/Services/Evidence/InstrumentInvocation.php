<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\PropositionCollection;
use App\Domain\Evidence\SearchObservation;
use App\Models\Receipt;
use DomainException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

/** Invocation identity is independent of content versions and Reinforcement count. */
final class InstrumentInvocation
{
    public function normalize(array $input): array
    {
        $kind = $input['kind'] ?? null;
        if ($kind === 'proposition_search') {
            return PropositionCollection::normalizeInput($input);
        }
        $field = $kind === 'web_search' ? 'query' : 'reference';
        if (! in_array($kind, ['hyperlink', 'citation', 'web_search'], true)
            || array_diff(array_keys($input), ['kind', $field]) !== [] || ! is_string($input[$field] ?? null)) {
            throw new DomainException('The Instrument input must match its kind.');
        }

        return ['kind' => $kind, $field => match ($kind) {
            'hyperlink' => $this->url($input[$field]),
            'citation' => trim($input[$field]),
            'web_search' => SearchObservation::normalizeQuery($input[$field]),
        }];
    }

    public function signature(array $input): string
    {
        return hash('sha256', 'instrument-invocation:v1:'.json_encode($this->normalize($input),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Read legacy rows without changing their stored invocation or Receipt. */
    public function fromReceipt(Receipt $receipt): ?array
    {
        $execution = $receipt->evidenceExecution;
        if ($execution?->type !== 'instrument' || $execution->status !== 'completed') {
            return null;
        }
        $input = $execution->input;
        if (! is_array($input) || ! isset($input['kind'])) {
            $input = ['kind' => $receipt->type,
                $receipt->type === 'web_search' ? 'query' : 'reference' => $execution->reference ?? $receipt->reference];
        }
        try {
            return $this->normalize($input);
        } catch (DomainException) {
            return null;
        }
    }

    private function url(string $url): string
    {
        // Reuse validation, not the deliberately coarser Reinforcement resource key.
        app(HyperlinkIdentity::class)->resource($url);
        $uri = new Uri(trim($url));
        $host = strtolower($uri->getHost());
        if (! str_starts_with($host, '[')) {
            $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
        }
        $path = preg_replace_callback('/%([a-f0-9]{2})/i', function ($match) {
            $character = chr(hexdec($match[1]));

            return preg_match('/[A-Za-z0-9._~-]/', $character) ? $character : strtoupper($match[0]);
        }, $uri->getPath());

        // Keep scheme, nondefault port, query bytes/order and fragment. Those can
        // name distinct resources; do not use host/path-only evidence identity.
        return (string) $uri->withHost($host)->withPath(UriResolver::removeDotSegments($path ?: '/'));
    }
}
