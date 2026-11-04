<?php

namespace App\Domain\Evidence;

use App\Exceptions\ArtifactReadingException;

/** Participant patterns are bare UTF-8 expressions. The delimiter and engine limits belong to the reader. */
final class ReadingParameters
{
    public const MAX_PATTERN_BYTES = 512;

    public static function validate(string $type, array $parameters): void
    {
        $regex = $type === 'regex';
        if (array_diff(array_keys($parameters), $regex ? ['pattern', 'offset', 'max_chars'] : ['offset', 'max_chars']) !== []
            || (array_key_exists('offset', $parameters) && (! is_int($parameters['offset']) || (! $regex && $parameters['offset'] < 0)))
            || (array_key_exists('max_chars', $parameters) && (! is_int($parameters['max_chars']) || $parameters['max_chars'] < 1))) {
            throw new ArtifactReadingException('invalid_parameters', 'Use integer character offsets and positive character preferences. Only regex offsets may be negative.');
        }
        if ($regex) {
            self::expression($parameters['pattern'] ?? null);
        }
    }

    public static function expression(mixed $pattern): string
    {
        if (! is_string($pattern) || $pattern === '' || strlen($pattern) > self::MAX_PATTERN_BYTES
            || ! mb_check_encoding($pattern, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $pattern)) {
            throw new ArtifactReadingException('invalid_parameters', 'Regex requires a UTF-8 pattern of 1–512 bytes without control characters.');
        }
        // Restrict extensions to an optional leading mode and noncapturing groups.
        // Byte escapes and match resets could return partial or reversed Unicode spans.
        $body = preg_replace('/^\(\?[ims]+\)/', '', $pattern);
        if (preg_match('/\(\*|\(\?(?!:)/', $body) || str_contains($pattern, '\\C') || str_contains($pattern, '\\K')) {
            throw new ArtifactReadingException('invalid_parameters', 'Unsupported regex extension. Use a bare pattern with optional leading (?ims) flags.');
        }
        $expression = "\x1f(*NO_JIT)(*LIMIT_MATCH=100000)(*LIMIT_DEPTH=128)(*LIMIT_HEAP=2048)".$pattern."\x1fun";
        if (@preg_match($expression, '') === false) {
            throw new ArtifactReadingException('invalid_parameters', 'Invalid or excessively complex regex.');
        }

        return $expression;
    }

    public static function relativeStart(int $start, int $offset, int $total): int
    {
        return $start + max(-$start, min($total - $start, $offset));
    }
}
