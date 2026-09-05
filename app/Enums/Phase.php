<?php

namespace App\Enums;

enum Phase: int
{
    case Distinguish = 1;
    case Persist = 2;
    case Constrain = 3;
    case Disperse = 4;
    case Mediate = 5;
    case Bias = 6;
    case Order = 7;
}
