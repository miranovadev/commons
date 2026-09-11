<?php

namespace App\Enums;

enum MeasurementAxis: string
{
    case Correspondence = 'correspondence';
    case Chirality = 'chirality';
    case Closure = 'closure';
    // Support/Refute lean is distinct from cross-Domain Alignment/Divergence.
    case Lean = 'lean';

    public function initialDirection(): ObservableDirection
    {
        return $this === self::Closure
            ? ObservableDirection::Negative : ObservableDirection::Positive;
    }
}
