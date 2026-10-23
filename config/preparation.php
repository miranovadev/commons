<?php

return [
    'source_bytes' => (int) env('PREPARATION_SOURCE_BYTES', 8 * 1024 * 1024),
    'selection_bytes' => (int) env('PREPARATION_SELECTION_BYTES', 16384),
    'delivery_bytes' => (int) env('PREPARATION_DELIVERY_BYTES', 49152),
    'max_chars' => (int) env('PREPARATION_MAX_CHARS', 12000),
    'text_budget' => (int) env('PREPARATION_TEXT_BUDGET', 12000),
    'representation_bytes' => 16 * 1024 * 1024,
    'pdf' => [
        'binary' => env('PREPARATION_PDF_BINARY', '/usr/bin/pdftotext'),
        'limiter' => env('PREPARATION_PDF_LIMITER', '/usr/bin/prlimit'),
        'timeout_seconds' => 10,
        'memory_bytes' => 256 * 1024 * 1024,
        'output_bytes' => 4 * 1024 * 1024,
    ],
    'unresolved_seconds' => 1800,
];
