<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Participant;
use App\Models\Role;
use App\Models\Substrate;
use App\Models\SubstrateMembership;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubstrateMembershipService
{
    public function __construct(
        private readonly ParticipantConnectionService $participantConnections,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function join(
        User $user,
        Substrate $substrate,
        array $attributes,
    ): Participant {
        return DB::transaction(function () use (
            $user,
            $substrate,
            $attributes,
        ): Participant {
            $connectionAttributes = Arr::pull($attributes, 'connection');

            $lockedSubstrate = Substrate::query()
                ->lockForUpdate()
                ->findOrFail($substrate->getKey());

            $maximum = $lockedSubstrate->maximumParticipants();

            if (
                $maximum !== null
                && $lockedSubstrate->participants()->count() >= $maximum
            ) {
                throw ValidationException::withMessages([
                    'substrate' => [
                        'The substrate has reached its maximum participants.',
                    ],
                ]);
            }

            $participant = new Participant;
            $participant->forceFill([
                ...$attributes,
                'substrate_id' => $lockedSubstrate->getKey(),
                'user_id' => $user->getKey(),
            ]);
            $participant->save();

            $membership = SubstrateMembership::query()
                ->where('substrate_id', $lockedSubstrate->getKey())
                ->where('user_id', $user->getKey())
                ->first() ?? new SubstrateMembership;

            $membership->forceFill([
                'substrate_id' => $lockedSubstrate->getKey(),
                'user_id' => $user->getKey(),
                'status' => 'active',
                'joined_at' => $membership->joined_at ?? now(),
            ]);

            // participant_id predates support for multiple Participants. Keep the
            // first Participant as the membership representative for backwards
            // compatibility; ownership itself lives on participants.user_id.
            if ($membership->participant_id === null) {
                $membership->participant_id = $participant->getKey();
            }

            $membership->save();

            $role = Role::query()
                ->where('name', config('authorization.roles.participant'))
                ->where('scope', 'substrate')
                ->firstOrFail();

            DB::table('role_user')->insertOrIgnore([
                'role_id' => $role->getKey(),
                'user_id' => $user->getKey(),
                'substrate_id' => $lockedSubstrate->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (is_array($connectionAttributes)) {
                $connection = Connection::query()
                    ->whereKey($connectionAttributes['id'])
                    ->whereBelongsTo($user)
                    ->firstOrFail();

                $this->participantConnections->create(
                    $participant,
                    $connection,
                    Arr::except($connectionAttributes, 'id'),
                );
            }

            return $participant;
        });
    }
}
