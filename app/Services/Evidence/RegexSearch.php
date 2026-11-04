<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\ReadingParameters;
use App\Exceptions\ArtifactReadingException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class RegexSearch
{
    public const MAX_MATCHES = 4096;

    private const MAX_RESULT_BYTES = 1048576;

    public static function executable(): string|false
    {
        return (new PhpExecutableFinder)->find(false);
    }

    /** The wall deadline also bounds unanchored searches across many possible starting positions. */
    public static function first(string $subject, string $pattern): ?array
    {
        $span = self::run($subject, $pattern);
        if ($span === null) {
            return null;
        }
        [$offset, $bytes] = $span;
        $start = mb_strlen(substr($subject, 0, $offset), 'UTF-8');

        return ['start' => $start, 'end' => $start + mb_strlen(substr($subject, $offset, $bytes), 'UTF-8')];
    }

    /** Build complete records before ordinary Preparation allocation selects a window. */
    public static function results(string $subject, array $parameters): array
    {
        if (strlen($subject) > 16 * 1024 * 1024) {
            throw new ArtifactReadingException('representation_limit', 'The readable source exceeds the 16 MiB regex enumeration ceiling. No search was performed.');
        }
        $include = min(self::MAX_MATCHES, $parameters['limit'] ?? self::MAX_MATCHES);
        $context = $parameters['context_chars'] ?? ReadingParameters::DEFAULT_CONTEXT;
        $search = self::run($subject, $parameters['pattern'], [(string) self::MAX_MATCHES, (string) $include]);
        $total = $search['total'];
        $prefix = 'Pattern: '.self::quote($parameters['pattern'])."\nTotal matches: "
            .($total ?? 'unknown (more than '.self::MAX_MATCHES.', enumeration ceiling reached)')."\n";
        $byteLimit = min(self::MAX_RESULT_BYTES, (int) config('preparation.representation_bytes'));
        $wanted = count($search['spans']);
        $limited = isset($parameters['limit']) && $wanted === $parameters['limit'] && ($total === null || $wanted < $total);
        $header = $prefix.'Included matches: '.$wanted.($limited ? ' (requested limit)' : '')."\n";
        $available = $byteLimit - strlen($header);
        $rows = [];
        $previousByte = $previousChar = 0;
        foreach ($search['spans'] as $index => [$start, $length]) {
            if ($length > $available) {
                break;
            }
            $match = substr($subject, $start, $length);
            $charStart = $previousChar + mb_strlen(substr($subject, $previousByte, $start - $previousByte), 'UTF-8');
            $charEnd = $charStart + mb_strlen($match, 'UTF-8');
            $end = $start + $length;
            $before = $after = '';
            if ($context > 0) {
                // Inspect at most four bytes per requested context character on each side.
                $bytes = min($start, 4 * $context);
                $prefixBytes = ltrim(substr($subject, $start - $bytes, $bytes), "\x80..\xbf");
                $before = mb_substr($prefixBytes, -$context, null, 'UTF-8');
                $after = mb_substr(substr($subject, $end, 4 * $context), 0, $context, 'UTF-8');
            }
            $row = "\n[".($index + 1)."] Source characters: [{$charStart}, {$charEnd})\n"
                .'Before: '.self::quote($before)."\nMatch: ".self::quote($match)."\nAfter: ".self::quote($after)."\n";
            if (strlen($row) > $available) {
                break;
            }
            $rows[] = $row;
            $available -= strlen($row);
            $previousByte = $end;
            $previousChar = $charEnd;
        }
        $byteLimited = count($rows) < $wanted;
        if ($byteLimited) {
            // A longer safety notice can displace a record. Keep only a complete prefix.
            $rowBytes = $byteLimit - strlen($header) - $available;
            do {
                $header = $prefix.'Included matches: '.count($rows)." (result byte ceiling reached)\n";
                if (strlen($header) + $rowBytes <= $byteLimit || $rows === []) {
                    break;
                }
                $rowBytes -= strlen(array_pop($rows));
            } while (true);
        }
        if (strlen($header) > $byteLimit) {
            throw new ArtifactReadingException('representation_limit', 'Even the regex result summary exceeds the representation byte ceiling.');
        }

        return ['content' => $header.implode('', $rows), 'result_set' => ['total_matches' => $total,
            'included_matches' => count($rows), 'byte_limited' => $byteLimited]];
    }

    private static function quote(string $text): string
    {
        return json_encode($text, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function run(string $subject, string $pattern, array $arguments = []): ?array
    {
        $expression = ReadingParameters::expression($pattern);
        $php = self::executable();
        if ($php === false) {
            throw new ArtifactReadingException('reader_failure', 'The local regex reader is unavailable.');
        }
        $process = new Process([$php, '-n', '-d', 'memory_limit=96M', base_path('scripts/readers/regex.php'), ...$arguments],
            input: $expression."\n".$subject, timeout: 2);
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ArtifactReadingException('invalid_parameters', 'Regex exceeded its execution deadline.');
        }
        if ($process->getExitCode() === 2) {
            throw new ArtifactReadingException('invalid_parameters', 'Regex exceeded its match or nesting limit.');
        }
        if (! $process->isSuccessful()) {
            throw new ArtifactReadingException('reader_failure', 'The local regex reader could not complete.');
        }

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
