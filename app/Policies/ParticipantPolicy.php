<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\User;

class ParticipantPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasSystemAccess() ? true : null;
    }

    public function viewConnections(
        User $user,
        Participant $participant,
    ): bool {
        return $this->ownsParticipant($user, $participant)
            && $user->hasPermission(
                'connections.manage',
                $participant->substrate,
            );
    }

    public function manageConnections(
        User $user,
        Participant $participant,
    ): bool {
        return $this->viewConnections($user, $participant);
    }

    private function ownsParticipant(
        User $user,
        Participant $participant,
    ): bool {
        return $participant->user_id === $user->getKey();
    }
}
