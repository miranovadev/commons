<?php

namespace App\Services;

use App\Enums\ConnectionTriggerMode;
use App\Models\ParticipantConnection;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class ParticipantConnectionTriggerService
{
    /**
     * Return enabled connections whose wall-clock trigger is ready.
     *
     * @return Collection<int, ParticipantConnection>
     */
    public function due(
        ?CarbonInterface $at = null,
        int $limit = 100,
    ): Collection {
        $at ??= now();

        return ParticipantConnection::query()
            ->where('is_enabled', true)
            ->whereNotNull('next_trigger_at')
            ->where('next_trigger_at', '<=', $at)
            ->orderBy('next_trigger_at')
            ->limit(max(1, $limit))
            ->get();
    }

    public function isDue(
        ParticipantConnection $connection,
        ?CarbonInterface $at = null,
    ): bool {
        $at ??= now();

        return $connection->is_enabled
            && $connection->next_trigger_at !== null
            && $connection->next_trigger_at->lte($at);
    }

    /**
     * Recalculate a connection's schedule after configuration changes.
     */
    public function reschedule(
        ParticipantConnection $connection,
        ?CarbonInterface $from = null,
    ): void {
        $from ??= now();

        $connection->forceFill([
            'next_trigger_at' => $this->nextTriggerAt($connection, $from),
        ])->save();
    }

    /**
     * Claim a due connection before waking its Participant.
     *
     * Advancing the schedule first prevents the same due occurrence from being
     * selected repeatedly while the Participant enters its next Cycle.
     */
    public function recordTriggered(
        ParticipantConnection $connection,
        ?CarbonInterface $at = null,
    ): void {
        $at ??= now();

        $connection->forceFill([
            'last_triggered_at' => $at,
            'next_trigger_at' => $this->nextTriggerAt($connection, $at),
            'last_error' => null,
        ])->save();
    }

    public function recordSuccess(ParticipantConnection $connection): void
    {
        $connection->forceFill([
            'last_error' => null,
        ])->save();
    }

    public function recordFailure(
        ParticipantConnection $connection,
        string $error,
    ): void {
        $connection->forceFill([
            'last_error' => $error,
        ])->save();
    }

    private function nextTriggerAt(
        ParticipantConnection $connection,
        CarbonInterface $from,
    ): ?CarbonInterface {
        if (! $connection->is_enabled) {
            return null;
        }

        $mode = ConnectionTriggerMode::tryFrom(
            data_get(
                $connection->configuration,
                'trigger.mode',
                ConnectionTriggerMode::Manual->value,
            ),
        ) ?? ConnectionTriggerMode::Manual;

        if ($mode === ConnectionTriggerMode::Manual) {
            return null;
        }

        $interval = max(
            1,
            (int) data_get(
                $connection->configuration,
                'trigger.interval_minutes',
                1,
            ),
        );

        return $from->copy()->addMinutes($interval);
    }
}
