<?php

namespace App\Services\Evidence;

use Closure;
use DomainException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/** Transport only: callers retain their own URL, observation and content semantics. */
class PublicResourceRetriever
{
    public function __construct(private PublicResourceAddress $addresses) {}

    /** @return array{response: Response, url: string} */
    public function retrieve(string $reference, Closure $validateUrl, int $maxBytes, string $label, string $accept = '*/*'): array
    {
        $url = $validateUrl($reference);
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $host = parse_url($url, PHP_URL_HOST);
            $address = $this->addresses->resolve($host);
            $port = parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80;
            $response = Http::connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->withHeaders(['Accept' => $accept, 'Accept-Encoding' => 'identity', 'User-Agent' => 'TheCommons-Verifier/1.0'])
                ->withOptions([
                    'proxy' => '', 'verify' => true, 'decode_content' => false,
                    // Validate every hop and pin DNS while retaining TLS hostname checks.
                    'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.(str_contains($address, ':') ? '['.$address.']' : $address)]],
                    'on_headers' => static function (ResponseInterface $response) use ($maxBytes, $label): void {
                        $length = $response->getHeaderLine('Content-Length');
                        if ($length !== '' && (! ctype_digit($length) || (float) $length > $maxBytes)) {
                            throw new DomainException($label.' resource exceeds the size limit or has invalid length metadata.');
                        }
                    },
                    // cURL's transfer callback aborts before an unbounded body can be buffered,
                    // including chunked responses without a Content-Length header.
                    'progress' => static function ($total, $received) use ($maxBytes, $label): void {
                        if ($total > $maxBytes || $received > $maxBytes) {
                            throw new DomainException($label.' resource exceeds the size limit.');
                        }
                    },
                ])->get($url);
            if ($response->redirect()) {
                if ($redirects === 3 || $response->header('Location') === '') {
                    throw new DomainException($label.' acquisition exceeded the redirect limit or lacks a destination.');
                }
                $url = $validateUrl((string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location'))));

                continue;
            }
            if (! $response->successful() || $response->status() === 206) {
                throw new DomainException($label.' resource could not be successfully retrieved.');
            }
            if (! in_array(strtolower($response->header('Content-Encoding')), ['', 'identity'], true)) {
                throw new DomainException($label.' acquisition requires an uncompressed bounded response.');
            }
            if (strlen($response->body()) > $maxBytes) {
                throw new DomainException($label.' resource exceeds the size limit.');
            }

            return ['response' => $response, 'url' => $url];
        }
        throw new DomainException($label.' acquisition exceeded the redirect limit.');
    }
}
