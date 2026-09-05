<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Role;
use App\Models\Substrate;
use App\Models\SubstrateMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SubstrateMembershipService
{
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
            $lockedSubstrate = Substrate::query()
                ->lockForUpdate()
                ->findOrFail($substrate->getKey());

            $existing = Participant::query()
                ->whereBelongsTo($lockedSubstrate)
                ->whereBelongsTo($user)
                ->first();

            if ($existing !== null) {
                throw new ConflictHttpException(
                    'The user already has a Participant in this substrate.',
                );
            }

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

            $membership = SubstrateMembership::query()->firstOrNew([
                'substrate_id' => $lockedSubstrate->getKey(),
                'user_id' => $user->getKey(),
            ]);
            $membership->forceFill([
                'participant_id' => $participant->getKey(),
                'status' => 'active',
                'joined_at' => now(),
            ]);
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

            return $participant;
        });
    }
}
