<?php

namespace App\Enums;

enum ConnectionTriggerMode: string
{
    case Interval = 'interval';
    case Manual = 'manual';
}
