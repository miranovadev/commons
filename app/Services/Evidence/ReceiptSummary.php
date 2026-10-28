<?php

namespace App\Services\Evidence;

use App\Models\Receipt;
use Illuminate\Support\Arr;

/** Bounded descriptions of immutable observations. Never reads Artifact storage. */
final class ReceiptSummary
{
    public const STRING_LIMIT = 240;

    public const JSON_LIMIT = 4096;

    public function forReceipt(Receipt $receipt): array
    {
        $metadata = $receipt->metadata ?? [];
        $summary = match ($receipt->type) {
            'hyperlink' => ['url' => $receipt->reference,
                ...Arr::only($metadata, ['title', 'description', 'content_verified'])],
            'web_search' => $this->search($metadata['observation'] ?? [], $receipt->reference),
            'proposition_search' => $this->search($metadata['proposition_collection'] ?? [], $receipt->reference),
            'citation' => ['target_id' => $receipt->reference],
            'preparation' => $this->preparation($metadata),
            'load_file' => [...Arr::only($metadata, ['method', 'source_url', 'filename', 'content_verified']),
                'artifact_count' => count($metadata['artifacts'] ?? []),
                'artifacts' => array_map(fn ($item) => Arr::only($item, ['hash', 'media_type', 'size']),
                    array_slice($metadata['artifacts'] ?? [], 0, 3))],
            'tragedy' => Arr::only($metadata, ['tragedy_id', 'governing_committal_id', 'terminal_proposition_id', 'opening_proposition_id']),
            default => $this->bounded($metadata, 2, 3),
        };
        // Diagnostic text may contain paths or remote responses. State the
        // recorded failure without copying arbitrary exceptions into summaries.
        if (isset($metadata['observation_error']) || isset($metadata['verification_error'])) {
            $summary['outcome'] = 'failed';
            $summary['content_verified'] = false;
        }
        $summary = $this->bounded($summary, 4, 12);
        // A final byte ceiling also bounds unusual historical metadata, escaping
        // and multi-byte strings. Field order is deterministic for every type.
        while (strlen(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) > self::JSON_LIMIT) {
            array_pop($summary);
        }

        return $summary ?: ['observation_type' => mb_substr((string) $receipt->type, 0, self::STRING_LIMIT)];
    }

    private function search(array $observation, string $reference): array
    {
        return ['query' => $observation['query'] ?? $reference,
            ...(isset($observation['results']) && is_array($observation['results']) ? ['result_count' => count($observation['results'])] : []),
            ...Arr::only($observation, ['provider', 'domain', 'head_proposition_id', 'limit'])];
    }

    private function preparation(array $metadata): array
    {
        $deliveries = $metadata['deliveries'] ?? [];
        $selections = $metadata['selections'] ?? [];

        return [...Arr::only($metadata, ['purpose', 'content_verified']),
            'outcome' => $metadata['outcome']['type'] ?? null,
            'selection_count' => count($selections),
            'selections' => array_map(fn ($item) => [
                ...Arr::only($item, ['artifact_id', 'read_type']),
                ...Arr::only($item['parameters'] ?? [], ['offset', 'max_chars']),
            ], array_slice($selections, 0, 3)),
            'delivery_count' => count($deliveries),
            'deliveries' => array_map(fn ($item) => [
                ...Arr::only($item, ['artifact_id', 'read_type']),
                ...Arr::only($item['boundaries'] ?? [], ['offset', 'length', 'total', 'has_more']),
            ], array_slice($deliveries, 0, 3)),
            ...(isset($metadata['failure']) ? ['failure' => Arr::only($metadata['failure'], ['stage', 'classification', 'method', 'version'])] : []),
        ];
    }

    /** First eligible properties in lexical order; recurse with the same budget. */
    private function bounded(array $values, int $depth, int $limit): array
    {
        $list = array_is_list($values);
        $childLimit = $limit;
        $limit = $list ? min($limit, 3) : $limit;
        if (! $list) {
            ksort($values, SORT_STRING);
        }
        $result = [];
        foreach ($values as $key => $value) {
            if (! $list && (strlen((string) $key) > 64
                || preg_match('/secret|credential|password|token|authorization|cookie|api.?key|access.?key|private|storage|path|body|raw|payload|content_excerpt|diagnostic|trace|exception|error|signature/i', (string) $key)
                || ($key !== 'content_verified' && preg_match('/(^|_)(content|html|binary|bytes|data|document)($|_)/i', (string) $key)))) {
                continue;
            }
            if (is_array($value)) {
                $value = $depth > 0 ? $this->bounded($value, $depth - 1, $childLimit) : [];
            } elseif (is_string($value)) {
                $value = $this->text($value);
            } elseif (! is_bool($value) && ! is_int($value) && ! is_float($value)) {
                continue;
            }
            if ($value === '' || $value === []) {
                continue;
            }
            if ($list) {
                $result[] = $value;
            } else {
                $result[$key] = $value;
            }
            if (count($result) === $limit) {
                break;
            }
        }

        return $result;
    }

    private function text(string $value): string
    {
        // Do not turn a compatibility summary into a credential/path channel.
        if (preg_match('~(?:^|\s)(?:/(?:home|root|tmp|var|workspace|private)/|[A-Za-z]:\\\\)|-----BEGIN |\b(?:Bearer|Basic)\s+\S+|(?:password|secret|token|api[_-]?key)\s*[=:]~i', $value)) {
            return '';
        }
        if (preg_match('~^https?://~i', $value)) {
            $parts = parse_url($value);
            if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
                return '';
            }
            // Signed URLs and query credentials are not descriptive metadata.
            if (preg_match('/(?:token|secret|password|signature|credential|api.?key)=/i', $parts['query'] ?? '')) {
                $value = preg_replace('/[?#].*$/s', '', $value);
            }
        }

        return mb_strlen($value) > self::STRING_LIMIT ? mb_substr($value, 0, self::STRING_LIMIT - 1).'…' : $value;
    }
}
