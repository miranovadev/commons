<?php

namespace App\Enums;

use InvalidArgumentException;

enum ContextDomain: string
{
    case Emergence = 'emergence';
    case Settlement = 'settlement';

    public static function after(
        float $tension,
        self $hystereticDirection,
    ): self {
        if (! is_finite($tension) || $tension < -1 || $tension > 1) {
            throw new InvalidArgumentException(
                'Tension must be between -1 and 1.'
            );
        }

        return match (true) {
            $tension > 0 => self::Emergence,
            $tension < 0 => self::Settlement,
            default => $hystereticDirection,
        };
    }
}
