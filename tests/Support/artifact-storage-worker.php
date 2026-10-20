<?php

use App\Services\Evidence\ArtifactStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

// Independent process, sharing only the test's private filesystem namespace.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['artifacts.disk' => 'artifacts', 'filesystems.disks.artifacts.root' => $argv[1]]);
// Force all writers to observe the absent final key before any can publish it.
$disk = Storage::disk('artifacts');
$barrier = $argv[2];
$synchronized = new class($disk->getDriver(), $disk->getAdapter(), $disk->getConfig()) extends FilesystemAdapter
{
    public string $barrier;

    private bool $waited = false;

    public function fileExists($path)
    {
        $exists = parent::fileExists($path);
        if (! $this->waited && ! $exists) {
            $this->waited = true;
            touch($this->barrier.'/'.getmypid());
            $deadline = microtime(true) + 10;
            while (count(glob($this->barrier.'/*')) < 4) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Artifact concurrency barrier timed out.');
                }
                usleep(1000);
            }
        }

        return $exists;
    }
};
$synchronized->barrier = $barrier;
Storage::set('artifacts', $synchronized);
echo json_encode(app(ArtifactStore::class)->preserve(str_repeat('concurrent-artifact', 65536), 'text/plain'), JSON_THROW_ON_ERROR);
