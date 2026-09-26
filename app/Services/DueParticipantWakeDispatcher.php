<?php

namespace App\Services;

use App\Jobs\WakeParticipant;
use App\Models\ParticipantConnection;
use Illuminate\Support\Facades\DB;

final readonly class DueParticipantWakeDispatcher
{
    public function __construct(
        private ParticipantConnectionTriggerService $triggers,
        private WakeOffsetResolver $offsets,
    ) {}

    public function dispatch(int $limit = 100): int
    {
        $dispatched = 0;

        foreach ($this->triggers->due(limit: $limit) as $candidate) {
            $claimed = DB::transaction(function () use ($candidate): ?array {
                $participantConnection = ParticipantConnection::query()
                    ->lockForUpdate()
                    ->find($candidate->getKey());

                if ($participantConnection === null
                    || ! $this->triggers->isDue($participantConnection)) {
                    return null;
                }

                $scheduledAt = $participantConnection->next_trigger_at;
                $claimedAt = now();
                $availableAt = $scheduledAt->copy()->addSeconds(
                    $this->offsets->seconds($participantConnection->configuration),
                );
                $this->triggers->recordTriggered($participantConnection, $claimedAt);

                return [$participantConnection->getKey(), $availableAt];
            });

            if (! $claimed) {
                continue;
            }

            WakeParticipant::dispatch($claimed[0])->delay($claimed[1]);
            $dispatched++;
        }

        return $dispatched;
    }
}

