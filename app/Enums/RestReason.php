<?php

namespace App\Enums;

enum RestReason: string
{
    case NoAvailableMove = 'no_available_move';
    case NoRemainingPotential = 'no_remaining_potential';
}
