<?php

namespace App\Services;

use App\Enums\ConnectionTriggerMode;
use App\Models\Participant;
use App\Models\ParticipantConnection;

final class ParticipantConnectionService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Participant $participant,
        array $attributes,
    ): ParticipantConnection {
        $connection = new ParticipantConnection;
        $connection->forceFill([
            'participant_id' => $participant->getKey(),
            'provider' => $attributes['provider'],
            'name' => $attributes['name'],
            'is_enabled' => $attributes['is_enabled'] ?? true,
            'configuration' => $this->configuration(
                $attributes['configuration'],
            ),
            'credentials' => $attributes['credentials'],
        ]);
        $connection->save();

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        ParticipantConnection $connection,
        array $attributes,
    ): ParticipantConnection {
        if (array_key_exists('configuration', $attributes)) {
            $attributes['configuration'] = $this->configuration(
                array_replace_recursive(
                    $connection->configuration,
                    $attributes['configuration'],
                ),
            );
        }

        $connection->forceFill($attributes);
        $connection->save();

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function configuration(array $configuration): array
    {
        $configuration = array_replace_recursive([
            'trigger' => [
                'mode' => ConnectionTriggerMode::EveryCycle->value,
                'interval' => 1,
            ],
        ], $configuration);

        $mode = ConnectionTriggerMode::from(
            $configuration['trigger']['mode'],
        );

        if ($mode === ConnectionTriggerMode::EveryCycle) {
            $configuration['trigger']['interval'] = 1;
        }

        if ($mode === ConnectionTriggerMode::Manual) {
            unset($configuration['trigger']['interval']);
        }

        return $configuration;
    }
}
