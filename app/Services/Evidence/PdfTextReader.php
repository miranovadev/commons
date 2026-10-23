<?php

namespace App\Services\Evidence;

use App\Exceptions\ArtifactReadingException;
use Symfony\Component\Process\Process;
use Throwable;

/** Local Poppler text extraction: fixed argv, private input, bounded subprocess. */
final class PdfTextReader
{
    public const METHOD = 'commons-poppler-text';

    private array $versions = [];

    public function version(): ?string
    {
        $binary = (string) config('preparation.pdf.binary');
        $limiter = (string) config('preparation.pdf.limiter');
        if (! str_starts_with($binary, '/') || ! is_executable($binary)
            || ! str_starts_with($limiter, '/') || ! is_executable($limiter)) {
            return null;
        }
        $key = $binary.':'.filemtime($binary).':'.$limiter;
        if (! array_key_exists($key, $this->versions)) {
            try {
                $process = new Process([$binary, '-v'], timeout: 2);
                $process->run();
                $this->versions[$key] = $process->isSuccessful()
                    && preg_match('/pdftotext version ([\d.]+)/', $process->getErrorOutput().$process->getOutput(), $match)
                    ? '1/poppler-'.$match[1] : null;
            } catch (Throwable) {
                $this->versions[$key] = null;
            }
        }

        return $this->versions[$key];
    }

    public function extract(string $body): string
    {
        if ($this->version() === null) {
            throw new ArtifactReadingException('missing_capability', 'The bounded Poppler PDF reader is unavailable.');
        }
        $directory = sys_get_temp_dir().'/commons-pdf-'.bin2hex(random_bytes(16));
        if (! mkdir($directory, 0700)) {
            throw new ArtifactReadingException('reader_failure', 'Cannot create private PDF processing storage.');
        }
        $process = null;
        $pipes = [];
        try {
            $input = $directory.'/source.pdf';
            if (file_put_contents($input, $body) !== strlen($body)) {
                throw new ArtifactReadingException('reader_failure', 'Cannot preserve the temporary PDF input.');
            }
            chmod($input, 0600);
            $seconds = max(1, min(30, (int) config('preparation.pdf.timeout_seconds')));
            $memory = max(32 * 1024 * 1024, min(512 * 1024 * 1024, (int) config('preparation.pdf.memory_bytes')));
            $limit = max(1, min((int) config('preparation.representation_bytes'), (int) config('preparation.pdf.output_bytes')));
            // pdftotext only extracts local document text; it does not run PDF actions,
            // scripts, attachments, remote URIs or converters. No document-controlled argv.
            $process = proc_open([(string) config('preparation.pdf.limiter'),
                '--as='.$memory, '--cpu='.$seconds, '--fsize=0', '--core=0', '--nofile=32', '--',
                (string) config('preparation.pdf.binary'), '-enc', 'UTF-8', '-eol', 'unix', '-nopgbrk', $input, '-'],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory,
                options: ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new ArtifactReadingException('reader_failure', 'Cannot start the bounded PDF extraction process.');
            }
            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }
            $started = hrtime(true);
            $output = '';
            $diagnosticBytes = 0;
            $exit = null;
            do {
                $status = proc_get_status($process);
                if (! $status['running'] && $exit === null) {
                    $exit = $status['exitcode'];
                }
                // Read at most one fixed-size chunk per stream before checking budgets
                // and time. A continuously writing child cannot monopolise a pipe drain.
                foreach ($pipes as $number => $pipe) {
                    $chunk = fread($pipe, 8192);
                    if ($chunk === false) {
                        throw new ArtifactReadingException('reader_failure', 'PDF output could not be read.');
                    }
                    if ($number === 1) {
                        if (strlen($output) + strlen($chunk) > $limit) {
                            throw new ArtifactReadingException('representation_limit', 'PDF extraction exceeds the bounded intermediate output limit.');
                        }
                        $output .= $chunk;
                    } else {
                        $diagnosticBytes += strlen($chunk);
                        if ($diagnosticBytes > 16384) {
                            throw new ArtifactReadingException('reader_failure', 'PDF extraction exceeded its diagnostic limit.');
                        }
                    }
                }
                if (hrtime(true) - $started >= $seconds * 1000000000) {
                    throw new ArtifactReadingException('reader_timeout', 'PDF text extraction exceeded its time limit.');
                }
                $pending = $status['running'] || ! feof($pipes[1]) || ! feof($pipes[2]);
                if ($pending) {
                    usleep(1000);
                }
            } while ($pending);
            if ($exit !== 0) {
                throw new ArtifactReadingException('reader_failure', 'PDF extraction failed or exceeded a process resource limit (exit '.($exit ?? 'unknown').').');
            }
            if (! mb_check_encoding($output, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0E-\x1F]/', $output)) {
                throw new ArtifactReadingException('invalid_source', 'PDF extraction did not yield supported UTF-8 text.');
            }
            if (trim($output) === '') {
                throw new ArtifactReadingException('no_extractable_text', 'This PDF yielded no usable text. OCR and image interpretation are not available.');
            }

            return $output;
        } catch (ArtifactReadingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ArtifactReadingException('reader_failure', 'The bounded PDF extraction process could not complete.');
        } finally {
            try {
                if (is_resource($process)) {
                    if (proc_get_status($process)['running']) {
                        proc_terminate($process, 9);
                    }
                    foreach ($pipes as $pipe) {
                        fclose($pipe);
                    }
                    proc_close($process);
                }
            } finally {
                if (is_file($directory.'/source.pdf')) {
                    unlink($directory.'/source.pdf');
                }
                rmdir($directory);
            }
        }
    }
}
