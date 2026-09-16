<?php

namespace App\Enums;

/** Domain-independent vocabulary slots for Correspondence, NOT producing Phase positions. */
enum RegisterPosition: string
{
    case First = 'R';
    case Second = 'G';
    case Third = 'B';

    public static function forRegister(string $register): self
    {
        return match ($register) {
            'activity', 'signal' => self::First,
            'frontier', 'stance' => self::Second,
            'canon', 'concord' => self::Third,
        };
    }

    public function registers(): array
    {
        return match ($this) {
            self::First => ['activity', 'signal'],
            self::Second => ['frontier', 'stance'],
            self::Third => ['canon', 'concord'],
        };
    }
}
