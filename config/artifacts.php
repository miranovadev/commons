<?php

return [
    // Keep this disk/root stable for the lifetime of historical Receipts.
    'disk' => env('ARTIFACT_FILESYSTEM_DISK', 'artifacts'),
];
