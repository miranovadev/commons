<?php

namespace App\Enums;

enum SupersessionStrength: string
{
    case Soft = 'S';
    case Hard = 'H';

    public static function for(string $type): self
    {
        return $type === 'variant' ? self::Soft : self::Hard;
    }
}
