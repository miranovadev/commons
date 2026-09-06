<?php

namespace App\Services;

use App\Jobs\WakeParticipant;
use App\Models\ParticipantConnection;
use Illuminate\Support\Facades\DB;

final readonly class DueParticipantWakeDispatcher
{
    public function __construct(
        private ParticipantConnectionTriggerService $triggers,
    ) {}

    public function dispatch(int $limit = 100): int
    {
        $dispatched = 0;

        foreach ($this->triggers->due(limit: $limit) as $candidate) {
            $claimed = DB::transaction(function () use ($candidate): bool {
                $participantConnection = ParticipantConnection::query()
                    ->lockForUpdate()
                    ->find($candidate->getKey());

                if ($participantConnection === null
                    || ! $this->triggers->isDue($participantConnection)) {
                    return false;
                }

                $this->triggers->recordTriggered($participantConnection);

                return true;
            });

            if (! $claimed) {
                continue;
            }

            WakeParticipant::dispatch($candidate->getKey());
            $dispatched++;
        }

        return $dispatched;
    }
}
