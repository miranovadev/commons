<?php

namespace App\Services;

use App\Enums\ConnectionTriggerMode;
use App\Support\WakeConfiguration;
use App\Models\Connection;
use App\Models\Participant;
use App\Models\ParticipantConnection;

final class ParticipantConnectionService
{
    public function __construct(
        private readonly ParticipantConnectionTriggerService $triggers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Participant $participant,
        Connection $connection,
        array $attributes = [],
    ): ParticipantConnection {
        $participantConnection = new ParticipantConnection;
        $participantConnection->forceFill([
            'participant_id' => $participant->getKey(),
            'connection_id' => $connection->getKey(),
            'is_enabled' => $attributes['is_enabled'] ?? true,
            'configuration' => $this->configuration(
                $attributes['configuration'] ?? [],
            ),
        ]);
        $participantConnection->save();

        $this->triggers->reschedule($participantConnection);

        return $participantConnection->refresh()->load('connection');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        ParticipantConnection $participantConnection,
        array $attributes,
    ): ParticipantConnection {
        $oldEnabled = $participantConnection->is_enabled;
        $oldTrigger = WakeConfiguration::resolve($participantConnection->configuration)['trigger'];

        if (array_key_exists('configuration', $attributes)) {
            $attributes['configuration'] = $this->configuration(
                array_replace_recursive(
                    $participantConnection->configuration ?? [],
                    $attributes['configuration'],
                ),
            );
        }

        $participantConnection->forceFill($attributes);
        $participantConnection->save();

        $newTrigger = WakeConfiguration::resolve($participantConnection->configuration)['trigger'];
        if ($oldEnabled !== $participantConnection->is_enabled
            || ($oldTrigger['mode'] ?? 'manual') !== $newTrigger['mode']
            || (int) ($oldTrigger['interval_minutes'] ?? 0) !== (int) ($newTrigger['interval_minutes'] ?? 0)) {
            $this->triggers->reschedule($participantConnection);
        }

        return $participantConnection->refresh()->load('connection');
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function configuration(array $configuration): array
    {
        $configuration = WakeConfiguration::resolve($configuration);

        $mode = ConnectionTriggerMode::from(
            $configuration['trigger']['mode'],
        );

        if ($mode === ConnectionTriggerMode::Manual) {
            unset($configuration['trigger']['interval_minutes']);
        }

        $configuration['wake']['timeout_seconds'] = (int) $configuration['wake']['timeout_seconds'];
        foreach (['seconds', 'random_from', 'random_to'] as $field) {
            $configuration['trigger']['offset'][$field] = (int) $configuration['trigger']['offset'][$field];
        }

        return [
            'trigger' => $configuration['trigger'],
            'wake' => $configuration['wake'],
        ];
    }
}

