<?php

/** Explicit opt-in diagnostic process only. Never loaded by application or normal suites. */
require __DIR__.'/../../vendor/autoload.php';

function diagnosticOverlay(string $relative, array $replacements): void
{
    $source = file_get_contents(__DIR__.'/../../'.$relative);
    foreach ($replacements as [$before,$after]) {
        if (substr_count($source, $before) !== 1) {
            throw new RuntimeException('Overlay source drift: '.$relative.' '.$before);
        }
        $source = str_replace($before, $after, $source);
    }
    eval(substr($source, 5));
}
// Formation candidate keeps ordinary numeric Chirality values. No production
// gate or direction override is loaded in this diagnostic.
require __DIR__.'/CandidateLocalMeasurements.php';
