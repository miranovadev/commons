<?php

namespace App\Enums;

enum ConnectionTriggerMode: string
{
    case EveryCycle = 'every_cycle';
    case CycleInterval = 'cycle_interval';
    case Manual = 'manual';
}
