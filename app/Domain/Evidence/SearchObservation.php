<?php

namespace App\Domain\Evidence;

use Carbon\CarbonImmutable;
use DomainException;

/** Provider-neutral, bounded observation. Retrieval says nothing about truth. */
final readonly class SearchObservation
{
    public const QUERY_LIMIT = 1000;

    public const RESULT_LIMIT = 5;

    public function __construct(
        public string $query,
        public CarbonImmutable $executedAt,
        public array $results,
        public string $provider,
        public ?string $requestId = null,
    ) {}

    public static function normalizeQuery(mixed $query): string
    {
        if (! is_string($query) || str_contains($query, "\0") || mb_strlen($query) > self::QUERY_LIMIT) {
            throw new DomainException('Web Search requires a query of 1–1000 characters.');
        }
        $normalized = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        if ($normalized === '') {
            throw new DomainException('Web Search requires a non-empty query.');
        }

        return $normalized;
    }

    public function evidence(): array
    {
        return ['query' => $this->query, 'executed_at' => $this->executedAt->toISOString(),
            'results' => $this->results, 'provider' => $this->provider, 'request_id' => $this->requestId];
    }

    public function signature(): string
    {
        // Execution time/request IDs distinguish events, not equivalent evidence.
        return hash('sha256', 'web-search:v1:'.json_encode([
            'query' => $this->query, 'results' => $this->results,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
