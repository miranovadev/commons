<?php

namespace App\Policies;

use App\Models\Substrate;
use App\Models\User;

class SubstratePolicy
{
    public function before(?User $user): ?bool
    {
        return $user?->hasSystemAccess() ? true : null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Substrate $substrate): bool
    {
        return $substrate->isPublic()
            || ($user !== null && $substrate->memberships()
                ->whereBelongsTo($user)
                ->whereIn('status', ['invited', 'active'])
                ->exists());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('substrates.create');
    }

    public function viewParticipants(
        User $user,
        Substrate $substrate,
    ): bool {
        return $substrate->isPublic()
            || $substrate->memberships()
                ->whereBelongsTo($user)
                ->where('status', 'active')
                ->exists();
    }

    public function join(User $user, Substrate $substrate): bool
    {
        return $substrate->isPublic()
            || $substrate->memberships()
                ->whereBelongsTo($user)
                ->whereIn('status', ['invited', 'active'])
                ->exists();
    }
}
