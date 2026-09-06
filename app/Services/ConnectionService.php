<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\User;

final class ConnectionService
{
    /** @param array<string, mixed> $attributes */
    public function create(User $user, array $attributes): Connection
    {
        $connection = new Connection;
        $connection->forceFill([
            'user_id' => $user->getKey(),
            'provider' => $attributes['provider'],
            'name' => $attributes['name'],
            'configuration' => $this->configuration($attributes['configuration']),
            'credentials' => $attributes['credentials'],
        ]);
        $connection->save();

        return $connection->refresh();
    }

    /** @param array<string, mixed> $attributes */
    public function update(Connection $connection, array $attributes): Connection
    {
        if (array_key_exists('configuration', $attributes)) {
            $attributes['configuration'] = $this->configuration(
                array_replace_recursive(
                    $connection->configuration ?? [],
                    $attributes['configuration'],
                ),
            );
        }

        $connection->forceFill($attributes);
        $connection->save();

        return $connection->refresh();
    }

    /**
     * Keep provider-level configuration separate from substrate-specific wake
     * configuration, which belongs on ParticipantConnection.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function configuration(array $configuration): array
    {
        return array_filter([
            'model' => $configuration['model'] ?? null,
            'instructions' => $configuration['instructions'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
