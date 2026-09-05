<?php

namespace App\Services;

use App\Enums\ConnectionTriggerMode;
use App\Models\Participant;
use App\Models\ParticipantConnection;

final class ParticipantConnectionService
{
    public function __construct(
        private readonly ParticipantConnectionTriggerService $triggers,
    ) {
    }

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

        $this->triggers->reschedule($connection);

        return $connection->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        ParticipantConnection $connection,
        array $attributes,
    ): ParticipantConnection {
        $scheduleChanged = array_key_exists('is_enabled', $attributes)
            || (
                array_key_exists('configuration', $attributes)
                && array_key_exists(
                    'trigger',
                    $attributes['configuration'],
                )
            );

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

        if ($scheduleChanged) {
            $this->triggers->reschedule($connection);
        }

        return $connection->refresh();
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function configuration(array $configuration): array
    {
        $configuration = array_replace_recursive([
            'trigger' => [
                'mode' => ConnectionTriggerMode::Manual->value,
            ],
        ], $configuration);

        $mode = ConnectionTriggerMode::from(
            $configuration['trigger']['mode'],
        );

        if ($mode === ConnectionTriggerMode::Manual) {
            unset($configuration['trigger']['interval_minutes']);
        }

        return $configuration;
    }
}
