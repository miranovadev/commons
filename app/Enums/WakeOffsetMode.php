<?php

namespace App\Enums;

enum WakeOffsetMode: string
{
    case Seconds = 'seconds';
    case Random = 'random';
}
