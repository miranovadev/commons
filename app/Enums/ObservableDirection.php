<?php

namespace App\Enums;

use InvalidArgumentException;

enum ObservableDirection: string
{
    case Positive = 'positive';
    case Negative = 'negative';

    public static function after(float $value, self $previous = self::Negative): self
    {
        if (! is_finite($value) || $value < -1 || $value > 1) {
            throw new InvalidArgumentException('An observable component must be between -1 and 1.');
        }

        return match (true) {
            $value > 0 => self::Positive,
            $value < 0 => self::Negative,
            default => $previous,
        };
    }
}
