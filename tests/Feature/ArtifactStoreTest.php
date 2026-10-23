<?php

namespace Tests\Feature;

use App\Exceptions\ArtifactStorageException;
use App\Services\Evidence\ArtifactStore;
use Aws\Command;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as S3Adapter;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ArtifactStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('artifacts');
    }

    public function test_exact_private_bytes_deduplicate_without_replacing_the_object(): void
    {
        $body = "<html>\r\n<script>alert('kept');</script><a href='https://example.com/out'>Link &amp; text</a></html>";
        $store = app(ArtifactStore::class);
        $first = $store->preserve($body, 'text/html');
        $disk = Storage::disk('artifacts');
        $inode = fileinode($disk->path($first['path']));
        $second = $store->preserve($body, 'text/html');
        $this->assertSame($first, $second);
        $this->assertSame($inode, fileinode($disk->path($first['path'])));
        $this->assertSame($body, $disk->get($first['path']));
        $this->assertSame('sha256:'.hash('sha256', $body), $first['hash']);
        $this->assertSame(strlen($body), $first['size']);
        $this->assertSame('private', $disk->getVisibility($first['path']));
        $this->assertCount(1, $disk->allFiles());
        $this->assertFalse(config('filesystems.disks.artifacts.serve'));
        $this->assertNotContains(config('filesystems.disks.artifacts.root'), config('filesystems.links'));
        $changed = $store->preserve($body.' ', 'text/html');
        $this->assertNotSame($first['path'], $changed['path']);
        $this->assertSame($body, $disk->get($first['path']));
    }

    public function test_existing_key_with_wrong_bytes_fails_without_overwriting(): void
    {
        $store = app(ArtifactStore::class);
        $artifact = $store->preserve('right', 'text/plain');
        Storage::disk('artifacts')->put($artifact['path'], 'wrong');
        try {
            $store->preserve('right', 'text/plain');
            $this->fail('Mismatched immutable key was accepted');
        } catch (ArtifactStorageException $error) {
            $this->assertStringContainsString('immutable key', $error->getMessage());
            $this->assertSame('wrong', Storage::disk('artifacts')->get($artifact['path']));
        }
    }

    public function test_failed_write_cannot_return_an_artifact(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('fileExists')->once()->andReturn(false);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        $manager = \Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->with('artifacts')->andReturn($disk);
        $this->expectException(ArtifactStorageException::class);
        (new ArtifactStore($manager))->preserve('body', 'text/plain');
    }

    public static function unsafeDisks(): array
    {
        return [[['visibility' => 'public']], [['serve' => true]], [['url' => 'https://public.example']], [['driver' => 'ftp']]];
    }

    #[DataProvider('unsafeDisks')]
    public function test_unsafe_disk_configuration_fails_closed(array $config): void
    {
        config(['filesystems.disks.artifacts' => [...config('filesystems.disks.artifacts'), ...$config]]);
        $this->expectException(ArtifactStorageException::class);
        app(ArtifactStore::class)->preserve('body', 'text/plain');
    }

    public function test_concurrent_processes_publish_one_identical_complete_object(): void
    {
        $root = Storage::disk('artifacts')->path('');
        $barrier = rtrim($root, '/').'-barrier';
        File::makeDirectory($barrier, 0700, true, true);
        $workers = [];
        try {
            for ($i = 0; $i < 4; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/artifact-storage-worker.php'), $root, $barrier]);
                $worker->setTimeout(20)->start();
                $workers[] = $worker;
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertCount(1, array_unique(array_column($results, 'path')));
            $this->assertCount(1, Storage::disk('artifacts')->allFiles());
            $this->assertSame(str_repeat('concurrent-artifact', 65536), Storage::disk('artifacts')->get($results[0]['path']));
        } finally {
            foreach ($workers as $worker) {
                $worker->stop();
            }
            File::deleteDirectory($barrier);
        }
    }

    public static function s3Races(): array
    {
        return [['created'], ['existing'], ['race-409'], ['race-412'], ['corrupt'], ['missing-after-write'], ['failed-write']];
    }

    #[DataProvider('s3Races')]
    public function test_s3_conditional_publication_and_readback(string $mode): void
    {
        $body = 'S3 exact bytes';
        $commands = [];
        $handler = new MockHandler;
        $missing = fn ($operation) => new S3Exception('missing', new Command($operation), ['code' => 'NotFound', 'response' => new Response(404)]);
        $handler->append($mode === 'existing' ? new Result : $missing('HeadObject'));
        if ($mode !== 'existing') {
            $handler->append(match ($mode) {
                'race-409' => new S3Exception('race', new Command('PutObject'), ['response' => new Response(409)]),
                'race-412' => new S3Exception('race', new Command('PutObject'), ['response' => new Response(412)]),
                'failed-write' => new S3Exception('failure', new Command('PutObject'), ['response' => new Response(500)]),
                default => new Result,
            });
        }
        if (str_starts_with($mode, 'race-')) {
            $handler->append(new Result);
        }
        $handler->append($mode === 'missing-after-write' ? $missing('GetObject') : new Result(['Body' => Utils::streamFor($mode === 'corrupt' ? 'bad' : $body)]));
        $client = new S3Client(['version' => 'latest', 'region' => 'us-east-1', 'credentials' => ['key' => 'fixture', 'secret' => 'fixture'], 'retries' => 0,
            'handler' => function ($command, $request) use ($handler, &$commands) {
                $commands[] = [$command->getName(), $command->toArray()];

                return $handler($command, $request);
            }]);
        $adapter = new S3Adapter($client, 'private-bucket', 'shared-root');
        $config = ['driver' => 's3', 'visibility' => 'private', 'serve' => false, 'bucket' => 'private-bucket', 'root' => 'shared-root', 'throw' => true];
        config(['artifacts.disk' => 'test-s3', 'filesystems.disks.test-s3' => $config]);
        Storage::set('test-s3', new AwsS3V3Adapter(new Filesystem($adapter), $adapter, $config, $client));
        try {
            $result = app(ArtifactStore::class)->preserve($body, 'text/plain');
            $this->assertNotContains($mode, ['corrupt', 'missing-after-write', 'failed-write']);
            $this->assertSame('sha256:'.hash('sha256', $body), $result['hash']);
        } catch (ArtifactStorageException) {
            $this->assertContains($mode, ['corrupt', 'missing-after-write', 'failed-write']);
        }
        $puts = array_values(array_filter($commands, fn ($c) => $c[0] === 'PutObject'));
        $this->assertCount($mode === 'existing' ? 0 : 1, $puts);
        if ($puts !== []) {
            $this->assertSame('*', $puts[0][1]['IfNoneMatch']);
            $this->assertStringStartsWith('shared-root/artifacts/sha256/', $puts[0][1]['Key']);
            $this->assertSame('private-bucket', $puts[0][1]['Bucket']);
            $this->assertSame('text/plain', $puts[0][1]['ContentType']);
        }
    }
}
