<?php

namespace App\Services;

class WakeClock
{
    public function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }
}
