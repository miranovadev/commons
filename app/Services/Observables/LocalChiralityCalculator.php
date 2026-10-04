<?php

namespace App\Services\Observables;

/** Promoted from the v3 natural-rhythm and proposition-local diagnostics. */
final readonly class LocalChiralityCalculator
{
    public function orientation(float $currentLean, float $previousLean): float
    {
        return round(($currentLean - $previousLean) / 2, 7);
    }

    /** Population CV; individual zero intervals remain genuine observations. */
    public function cadence(array $intervals): array
    {
        $count = count($intervals);
        $mean = $count === 0 ? null : array_sum($intervals) / $count;
        $sd = $mean === null ? null : sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $intervals)) / $count);
        $rawCv = $mean === null || $mean == 0 ? null : $sd / $mean;
        $zeroMeanRegular = $count >= 2 && $mean == 0 && $sd == 0;
        $cv = $zeroMeanRegular ? 0.0 : $rawCv;

        return ['mature' => $count >= 2, 'mean' => $mean, 'sd' => $sd, 'raw_cv' => $rawCv,
            'cv' => $cv, 'zero_mean_regular' => $zeroMeanRegular,
            'value' => $count < 2 ? 0.0 : round(max(-1, min(1, (1 - $cv) / (1 + $cv))), 7)];
    }
}
