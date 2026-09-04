<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\Participant;
use DomainException;

final class CycleService
{
    public function currentFor(Participant $participant): Cycle
    {
        $cycle = Cycle::query()
            ->whereBelongsTo($participant)
            ->whereNull('completed_at')
            ->latest('sequence')
            ->first();

        if ($cycle === null) {
            throw new DomainException(
                "Participant {$participant->getKey()} has no active cycle."
            );
        }

        return $cycle;
    }
}
