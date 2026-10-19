<?php

namespace App\Services\Evidence;

use App\Exceptions\ArtifactStorageException;
use Aws\S3\Exception\S3Exception;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

/** Private content-addressed publication. Never replace or delete a published key. */
class ArtifactStore
{
    public function __construct(private FilesystemManager $filesystems) {}

    /** @return array{path: string, hash: string, media_type: string, size: int} */
    public function preserve(string $body, string $mediaType): array
    {
        $hash = hash('sha256', $body);
        $path = 'artifacts/sha256/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
        try {
            $name = config('artifacts.disk');
            $config = config('filesystems.disks.'.$name, []);
            if (($config['visibility'] ?? null) !== 'private' || ($config['serve'] ?? false)
                || ! empty($config['url']) || ! in_array($config['driver'] ?? null, ['local', 's3'], true)) {
                throw new ArtifactStorageException('Artifacts require a private, non-serving local or S3 disk.');
            }
            $disk = $this->filesystems->disk($name);
            if (! $disk->fileExists($path)) {
                if ($config['driver'] === 'local') {
                    $this->publishLocal($disk, $path, $body);
                } elseif ($disk instanceof AwsS3V3Adapter) {
                    $this->publishS3($disk, $path, $body, $mediaType, $config);
                } else {
                    throw new ArtifactStorageException('Unsupported Artifact storage adapter.');
                }
            }
            // Existing objects are not trusted just because their key looks like a hash.
            // Read back new publications too: a Receipt must not outlive a failed write.
            $this->verify($disk, $path, $hash, strlen($body));
        } catch (ArtifactStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ArtifactStorageException('Artifact persistence failed.', previous: $exception);
        }

        return ['path' => $path, 'hash' => 'sha256:'.$hash, 'media_type' => $mediaType, 'size' => strlen($body)];
    }

    private function publishLocal(FilesystemAdapter $disk, string $path, string $body): void
    {
        // A completed private temporary object is linked atomically without replacement.
        // rename() would overwrite a concurrent winner; writing the final key exposes partial bytes.
        $temporary = dirname($path).'/.pending-'.bin2hex(random_bytes(16));
        try {
            if (! $disk->put($temporary, $body, ['visibility' => 'private'])) {
                throw new ArtifactStorageException('Artifact persistence failed.');
            }
            if (! @link($disk->path($temporary), $disk->path($path)) && ! $disk->fileExists($path)) {
                throw new ArtifactStorageException('Atomic Artifact publication failed.');
            }
        } finally {
            // This is only the unpublished temporary name, never a content-addressed key.
            $disk->delete($temporary);
        }
    }

    private function publishS3(AwsS3V3Adapter $disk, string $path, string $body, string $mediaType, array $config): void
    {
        try {
            // Flysystem's generic put() overwrites. Use the configured disk's client
            // for S3's atomic conditional creation; bucket policy must deny public access.
            $disk->getClient()->putObject([
                'Bucket' => $config['bucket'], 'Key' => $disk->path($path),
                'Body' => $body, 'ContentType' => $mediaType, 'IfNoneMatch' => '*',
            ]);
        } catch (S3Exception $exception) {
            if (! in_array($exception->getStatusCode(), [409, 412], true) || ! $disk->fileExists($path)) {
                throw $exception;
            }
            // A concurrent writer won. The caller verifies its exact bytes before reuse.
        }
    }

    private function verify(FilesystemAdapter $disk, string $path, string $expectedHash, int $expectedSize): void
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new ArtifactStorageException('Artifact could not be verified after persistence.');
        }
        try {
            $hash = hash_init('sha256');
            $size = 0;
            while (! feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                    throw new ArtifactStorageException('Artifact verification read failed.');
                }
                $size += strlen($chunk);
                if ($size > $expectedSize) {
                    throw new ArtifactStorageException('Artifact content does not match its immutable key.');
                }
                hash_update($hash, $chunk);
            }
            if ($size !== $expectedSize || ! hash_equals($expectedHash, hash_final($hash))) {
                throw new ArtifactStorageException('Artifact content does not match its immutable key.');
            }
        } finally {
            fclose($stream);
        }
    }
}
