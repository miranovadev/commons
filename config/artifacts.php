<?php

return [
    // Keep this disk/root stable for the lifetime of historical Receipts.
    // Acquisition/storage ceiling, independent of Preparation's cognitive budgets.
    'download_max_bytes' => (int) env('COMMONS_DOWNLOAD_MAX_BYTES', 16 * 1024 * 1024),
    'disk' => env('ARTIFACT_FILESYSTEM_DISK', 'artifacts'),
];
