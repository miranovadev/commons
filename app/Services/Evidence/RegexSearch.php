<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\ReadingParameters;
use App\Exceptions\ArtifactReadingException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class RegexSearch
{
    public static function executable(): string|false
    {
        return (new PhpExecutableFinder)->find(false);
    }

    /** The wall deadline also bounds unanchored searches across many possible starting positions. */
    public static function first(string $subject, string $pattern): ?array
    {
        $expression = ReadingParameters::expression($pattern);
        $php = self::executable();
        if ($php === false) {
            throw new ArtifactReadingException('reader_failure', 'The local regex reader is unavailable.');
        }
        $process = new Process([$php, '-n', '-d', 'memory_limit=96M', base_path('scripts/readers/first-match.php')],
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
        $span = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        if ($span === null) {
            return null;
        }
        [$offset, $bytes] = $span;
        $start = mb_strlen(substr($subject, 0, $offset), 'UTF-8');

        return ['start' => $start, 'end' => $start + mb_strlen(substr($subject, $offset, $bytes), 'UTF-8')];
    }
}
