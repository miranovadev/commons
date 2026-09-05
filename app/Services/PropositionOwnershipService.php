<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Proposition;

final class PropositionOwnershipService
{
    public function ownedBy(
        Proposition $proposition,
        Participant $participant,
    ): bool {
        return $proposition->phase()
            ->whereHas(
                'cycle',
                fn ($query) => $query->whereBelongsTo($participant),
            )
            ->exists();
    }
}
