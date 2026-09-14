<?php

namespace App\Services\Evidence;

use App\Contracts\WebSearchProvider;
use App\Domain\Evidence\SearchObservation;
use Carbon\CarbonImmutable;
use DomainException;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Http;

final class TavilyWebSearchProvider implements WebSearchProvider
{
    public function search(string $query): SearchObservation
    {
        $query = SearchObservation::normalizeQuery($query);
        if (config('services.web_search.provider') !== 'tavily'
            || trim((string) config('services.tavily.api_key')) === '') {
            throw new DomainException('Web Search is not configured.');
        }

        try {
            $response = Http::withToken(config('services.tavily.api_key'))->acceptJson()
                ->connectTimeout(3)->timeout(10)
                ->withOptions(['allow_redirects' => false, 'proxy' => '', 'verify' => true,
                    'on_headers' => function ($response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > 262144) {
                            throw new DomainException('Search response too large.');
                        }
                    },
                    'progress' => function ($total, $downloaded): void {
                        if ($downloaded > 262144) {
                            throw new DomainException('Search response too large.');
                        }
                    }])
                ->post('https://api.tavily.com/search', [
                    'query' => $query, 'topic' => 'general', 'search_depth' => 'basic',
                    'max_results' => SearchObservation::RESULT_LIMIT,
                    'include_answer' => false, 'include_raw_content' => false,
                    'include_images' => false, 'auto_parameters' => false,
                ]);
            if (! $response->successful() || strlen($response->body()) > 262144) {
                throw new DomainException('Search request failed.');
            }
            $body = $response->json();
            if (! is_array($body) || ! isset($body['results']) || ! is_array($body['results']) || ! array_is_list($body['results'])) {
                throw new DomainException('Invalid search response.');
            }
            $results = [];
            foreach (array_slice($body['results'], 0, SearchObservation::RESULT_LIMIT) as $index => $result) {
                if (! is_array($result) || ! is_string($result['url'] ?? null)
                    || ! is_string($result['title'] ?? null) || ! is_string($result['content'] ?? null)) {
                    throw new DomainException('Invalid search result.');
                }
                $url = $this->canonicalUrl($result['url']);
                $results[] = [
                    'title' => $this->text($result['title'], 300), 'url' => $url,
                    'excerpt' => $this->text($result['content'], 1200),
                    'excerpt_truncated' => mb_strlen($result['content']) > 1200,
                    'source' => (new Uri($url))->getHost(), 'rank' => $index + 1,
                    'relevance' => is_numeric($result['score'] ?? null) && is_finite((float) $result['score']) ? (float) $result['score'] : null,
                    'published_at' => is_string($result['published_date'] ?? null) ? $this->text($result['published_date'], 100) : null,
                ];
            }

            return new SearchObservation($query, CarbonImmutable::now(), $results, 'tavily',
                is_string($body['request_id'] ?? null) ? $this->text($body['request_id'], 200) : null);
        } catch (\Throwable) {
            // Never disclose provider bodies, request headers, credentials or transport errors.
            throw new DomainException('Web Search is temporarily unavailable.');
        }
    }

    private function text(string $value, int $limit): string
    {
        // PostgreSQL JSONB cannot represent NUL; reject malformed observations
        // at the provider boundary rather than failing Receipt persistence.
        if (str_contains($value, "\0")) {
            throw new DomainException('Invalid search text.');
        }

        return mb_substr($value, 0, $limit);
    }

    private function canonicalUrl(string $url): string
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new DomainException('Invalid search URL.');
        }
        $uri = new Uri($url);
        if (! in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getHost() === '' || $uri->getUserInfo() !== '') {
            throw new DomainException('Invalid search URL.');
        }

        // Search discovers resources; do not fetch them or strip meaningful query parameters.
        return (string) $uri->withFragment('');
    }
}
