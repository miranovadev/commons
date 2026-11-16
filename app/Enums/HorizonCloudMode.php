<?php

namespace App\Enums;

/** A participation orientation, not a Domain, measurement, or action permission. */
enum HorizonCloudMode: string
{
    case Dwell = 'dwell';
    case Journey = 'journey';
}
