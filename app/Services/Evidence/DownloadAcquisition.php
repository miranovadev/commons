<?php

namespace App\Services\Evidence;

use App\Domain\Evidence\DownloadObservation;
use DomainException;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Download intent preserves bytes; it never describes or reads their contents. */
class DownloadAcquisition
{
    public function __construct(private PublicResourceRetriever $retriever, private ArtifactStore $artifacts) {}

    public static function url(string $reference): string
    {
        $reference = trim($reference);
        $parts = parse_url($reference);
        if (strlen($reference) > 4096 || ! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20\x7f\\\\]/', $reference)) {
            throw new DomainException('Download requires a bounded HTTP(S) URL without credentials.');
        }
        $uri = new Uri($reference);
        if ($uri->getPort() !== null) {
            throw new DomainException('Download supports standard HTTP(S) ports only.');
        }
        $host = strtolower(rtrim($uri->getHost(), '.'));
        if (! str_starts_with($host, '[')) {
            $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
        }

        // Preserve path escaping and query bytes/order. Fragments are never transmitted.
        return (string) $uri->withHost($host)->withPath($uri->getPath() ?: '/')->withFragment('');
    }

    public function acquire(string $reference): DownloadObservation
    {
        if (DB::transactionLevel() > 0) {
            throw new DomainException('Download acquisition must occur outside database write transactions.');
        }
        $source = self::url($reference);
        $started = hrtime(true);
        $metadata = ['method' => 'download', 'source_url' => $source];
        try {
            ['response' => $response, 'url' => $url] = $this->retriever->retrieve(
                $source, self::url(...), max(1, (int) config('artifacts.download_max_bytes')), 'Download',
            );
        } catch (Throwable $exception) {
            return new DownloadObservation($source, [...$metadata, 'content_verified' => false,
                'validation' => 'download-failed', 'observation_error' => $exception instanceof DomainException
                    ? mb_substr($exception->getMessage(), 0, 300) : 'Download retrieval failed.',
                'processing_ms' => $this->elapsed($started)]);
        }
        $mediaType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
        if (! preg_match('~^[a-z0-9!#$&^_.+\-]+/[a-z0-9!#$&^_.+\-]+$~D', $mediaType)) {
            $mediaType = 'application/octet-stream';
        }
        // Storage/integrity failures propagate. They are never failed resource observations.
        $artifact = $this->artifacts->preserve($response->body(), $mediaType);

        return new DownloadObservation($source, [...$metadata, 'effective_url' => $url,
            'filename' => $this->filename($response->header('Content-Disposition'), $url),
            'artifacts' => [$artifact], 'content_verified' => true, 'processing_ms' => $this->elapsed($started)]);
    }

    private function elapsed(int $started): int
    {
        return min(2147483647, max(0, (int) ((hrtime(true) - $started) / 1000000)));
    }

    private function filename(string $header, string $url): ?string
    {
        $name = null;
        if (preg_match('/^\s*(?:attachment|inline)\s*(?:;|$)/i', $header)) {
            if (preg_match("/;\\s*filename\\*=UTF-8'[^']*'([^;]+)/i", $header, $match)) {
                $name = rawurldecode(trim($match[1]));
            } elseif (preg_match('/;\s*filename=(?:"([^"\r\n]*)"|([^;\s]+))/i', $header, $match)) {
                $name = $match[1] !== '' ? $match[1] : ($match[2] ?? null);
            }
        }
        $name ??= rawurldecode(basename(parse_url($url, PHP_URL_PATH) ?: ''));
        // Metadata only: discard paths, controls and misleading direction controls.
        $name = basename(str_replace('\\', '/', $name));
        if (! mb_check_encoding($name, 'UTF-8')) {
            return null;
        }
        $name = trim(preg_replace('/[\p{Cc}\p{Cf}]/u', '', $name) ?? '');

        return $name === '' || in_array($name, ['.', '..', '/'], true) ? null : mb_substr($name, 0, 255);
    }
}
