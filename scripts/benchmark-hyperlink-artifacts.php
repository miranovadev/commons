<?php

use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\HyperlinkMetadataExtractor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

/** Offline microbenchmark: no HTTP, database or provider calls. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root = sys_get_temp_dir().'/commons-artifact-benchmark-'.bin2hex(random_bytes(12));
config(['artifacts.disk' => 'artifact-benchmark', 'filesystems.disks.artifact-benchmark' => [
    'driver' => 'local', 'root' => $root, 'visibility' => 'private', 'directory_visibility' => 'private', 'serve' => false, 'throw' => true,
]]);
$store = app(ArtifactStore::class);
$extractor = app(HyperlinkMetadataExtractor::class);
$median = function (array $samples): float {
    sort($samples);

    return round(($samples[9] + $samples[10]) / 2, 3);
};
$results = [];
try {
    foreach ([48 * 1024, 512 * 1024, 2 * 1024 * 1024] as $size) {
        $prefix = '<!--00--><html><head><title>Artifact benchmark</title><meta name="description" content="Original description"></head><body><a href="https://example.test/out">Original link</a>';
        $body = $prefix.str_repeat('x', $size - strlen($prefix) - strlen('</body></html>')).'</body></html>';
        $samples = ['normalization_and_fingerprint_ms' => [], 'new_artifact_ms' => [], 'existing_artifact_ms' => [], 'metadata_ms' => []];
        for ($i = 0; $i < 20; $i++) {
            // Fixed-length variation creates distinct byte keys without changing visible text.
            $original = substr_replace($body, sprintf('%02d', $i), 4, 2);
            $start = hrtime(true);
            $normalized = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $original);
            $normalized = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
            hash('sha256', $normalized);
            $excerpt = mb_substr(mb_convert_encoding($normalized, 'UTF-8', 'UTF-8'), 0, 1200);
            $samples['normalization_and_fingerprint_ms'][] = (hrtime(true) - $start) / 1e6;
            foreach (['new_artifact_ms', 'existing_artifact_ms'] as $label) {
                $start = hrtime(true);
                $store->preserve($original, 'text/html');
                $samples[$label][] = (hrtime(true) - $start) / 1e6;
            }
            $start = hrtime(true);
            $extractor->extract($original, 'text/html', $excerpt, true);
            $samples['metadata_ms'][] = (hrtime(true) - $start) / 1e6;
        }
        $results[] = ['bytes' => $size, 'samples' => 20, ...array_map($median, $samples)];
    }
    echo json_encode(['runtime' => PHP_VERSION, 'backend' => 'private local filesystem', 'timing' => 'median milliseconds; HTTP and database excluded', 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} finally {
    // Only this uniquely created benchmark directory; never the configured Artifact disk.
    Storage::disk('artifact-benchmark')->deleteDirectory('');
}
