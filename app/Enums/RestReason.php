<?php

namespace App\Enums;

enum RestReason: string
{
    case NoAvailableMove = 'no_available_move';

    // Historical Cycles retain their originally recorded reason.
    case NoRemainingPotential = 'no_remaining_potential';
}
