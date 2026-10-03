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
// Preserve unavailable values/directions through the existing measurement pipeline.
// The mean's 0 contribution is not a Cadence observation.
diagnosticOverlay('app/Domain/Observables/ChiralityValues.php', [
    ['public float $cadence,', 'public ?float $cadence,'],
    ['($this->orientation + $this->cadence) / 2', '($this->orientation + ($this->cadence ?? 0.0)) / 2'],
    ["'attestation_cadence_direction' => Direction::after(\$this->cadence, \$cadence)->value", "'attestation_cadence_direction' => \$this->cadence === null ? null : Direction::after(\$this->cadence, \$cadence)->value"],
]);
// This class implements only the component gate. Source/evidence/Receipt authority
// lives in the unchanged callers and remains in effect.
diagnosticOverlay('app/Services/ChiralityGateService.php', [
    ['$admissible = $available && $orientation === $cadence;', '$admissible = $orientation !== null && ($cadence === null || $orientation === $cadence);'],
]);
require __DIR__.'/CandidateLocalMeasurements.php';
