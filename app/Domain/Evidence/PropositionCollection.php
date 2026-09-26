<?php

namespace App\Domain\Evidence;

use DomainException;

/** Bounded internal history observed from one Proposition head. */
final readonly class PropositionCollection
{
    public const QUERY_LIMIT = 1000;
    public const DEFAULT_LIMIT = 5;
    public const MIN_LIMIT = 1;
    public const MAX_LIMIT = 10;

    public function __construct(
        public ?string $query,
        public int $limit,
        public string $headPropositionId,
        public string $domain,
        public array $results,
    ) {}

    /** @return array{kind: string, query?: string, limit: int} */
    public static function normalizeInput(array $input): array
    {
        if (($input['kind'] ?? null) !== 'proposition_search'
            || array_diff(array_keys($input), ['kind', 'query', 'limit']) !== []) {
            throw new DomainException('Proposition Search accepts only an optional query and limit.');
        }

        $query = null;
        if (array_key_exists('query', $input)) {
            if (! is_string($input['query']) || str_contains($input['query'], "\0")
                || mb_strlen($input['query']) > self::QUERY_LIMIT) {
                throw new DomainException('Proposition Search query must be 1–1000 characters.');
            }
            $query = trim(preg_replace('/\s+/u', ' ', $input['query']) ?? '');
            if ($query === '') {
                throw new DomainException('Proposition Search query must be non-empty when supplied.');
            }
        }

        $limit = $input['limit'] ?? self::DEFAULT_LIMIT;
        if (! is_int($limit) || $limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw new DomainException('Proposition Search limit must be an integer from 1 to 10.');
        }

        return [
            'kind' => 'proposition_search',
            ...($query === null ? [] : ['query' => $query]),
            'limit' => $limit,
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'query' => $this->query,
            'limit' => $this->limit,
            'head_proposition_id' => $this->headPropositionId,
            'domain' => $this->domain,
            'results' => $this->results,
        ];
    }

    public function identity(): string
    {
        return hash('sha256', 'proposition-search:v1:'.json_encode([
            'query' => $this->query,
            'head_proposition_id' => $this->headPropositionId,
            'domain' => $this->domain,
            'results' => array_map(fn (array $result): array => [
                'proposition_id' => $result['proposition_id'],
                'lineage_distance' => $result['lineage_distance'],
            ], $this->results),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
