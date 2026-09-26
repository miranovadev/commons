<?php

namespace App\Enums;

enum WakeTermination: string
{
    case Phase = 'phase';
    case Cycle = 'cycle';
    case Rest = 'rest';
}
