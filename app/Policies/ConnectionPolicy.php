<?php

namespace App\Policies;

use App\Models\Connection;
use App\Models\User;

class ConnectionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasSystemAccess() ? true : null;
    }

    public function view(User $user, Connection $connection): bool
    {
        return $connection->user_id === $user->getKey();
    }

    public function update(User $user, Connection $connection): bool
    {
        return $this->view($user, $connection);
    }

    public function delete(User $user, Connection $connection): bool
    {
        return $this->view($user, $connection);
    }
}
