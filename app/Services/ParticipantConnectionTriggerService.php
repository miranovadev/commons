<?php

namespace App\Services;

use App\Enums\ConnectionTriggerMode;
use App\Models\Cycle;
use App\Models\ParticipantConnection;
use Illuminate\Support\Collection;

final class ParticipantConnectionTriggerService
{
    /**
     * @return Collection<int, ParticipantConnection>
     */
    public function dueForCycle(Cycle $cycle): Collection
    {
        return $cycle->participant
            ->connections()
            ->where('is_enabled', true)
            ->get()
            ->filter(
                fn (ParticipantConnection $connection): bool =>
                    $this->isDue($connection, $cycle),
            )
            ->values();
    }

    public function isDue(
        ParticipantConnection $connection,
        Cycle $cycle,
    ): bool {
        if (
            ! $connection->is_enabled
            || $connection->participant_id !== $cycle->participant_id
            || $connection->last_triggered_cycle_id === $cycle->getKey()
        ) {
            return false;
        }

        $mode = ConnectionTriggerMode::tryFrom(
            data_get(
                $connection->configuration,
                'trigger.mode',
                ConnectionTriggerMode::EveryCycle->value,
            ),
        ) ?? ConnectionTriggerMode::EveryCycle;

        if ($mode === ConnectionTriggerMode::Manual) {
            return false;
        }

        if ($mode === ConnectionTriggerMode::EveryCycle) {
            return true;
        }

        $lastSequence = $connection->lastTriggeredCycle?->sequence;

        if ($lastSequence === null) {
            return true;
        }

        $interval = max(
            2,
            (int) data_get(
                $connection->configuration,
                'trigger.interval',
                2,
            ),
        );

        return $cycle->sequence - $lastSequence >= $interval;
    }

    public function recordSuccess(
        ParticipantConnection $connection,
        Cycle $cycle,
    ): void {
        $connection->forceFill([
            'last_triggered_cycle_id' => $cycle->getKey(),
            'last_triggered_at' => now(),
            'last_error' => null,
        ])->save();
        $connection->unsetRelation('lastTriggeredCycle');
    }

    public function recordFailure(
        ParticipantConnection $connection,
        Cycle $cycle,
        string $error,
    ): void {
        $connection->forceFill([
            'last_triggered_cycle_id' => $cycle->getKey(),
            'last_triggered_at' => now(),
            'last_error' => $error,
        ])->save();
        $connection->unsetRelation('lastTriggeredCycle');
    }
}
