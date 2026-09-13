<?php

namespace App\Jobs;

use App\Contracts\ParticipantInference;
use App\Models\ParticipantConnection;
use App\Services\ParticipantConnectionTriggerService;
use App\Services\ParticipationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class WakeParticipant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $participantConnectionId,
    ) {
        $this->onQueue('participants');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->participantConnectionId))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 30),
        ];
    }

    public function handle(
        ParticipantInference $inference,
        ParticipationService $participation,
        ParticipantConnectionTriggerService $triggers,
    ): void {
        $participantConnection = ParticipantConnection::query()
            ->with(['connection', 'participant'])
            ->find($this->participantConnectionId);

        if ($participantConnection === null
            || ! $participantConnection->is_enabled) {
            return;
        }

        try {
            $state = $participation->stateFor(
                $participantConnection->participant,
            );

            if (($state['status'] ?? null) === 'waiting'
                && in_array(data_get($state, 'entry.mode'), ['continuity', 'potential'], true)
                && data_get($state, 'entry.available') === true) {
                $state = $participation->enter(
                    $participantConnection->participant,
                );
            }

            if (($state['status'] ?? null) !== 'ready') {
                $reason = data_get(
                    $state,
                    'reason.code',
                    'participant_not_ready',
                );
                $triggers->recordFailure(
                    $participantConnection,
                    "Participant wake skipped: {$reason}.",
                );

                return;
            }

            $contribution = $inference->contribute(
                $participantConnection->connection,
                $state,
            );
            $participation->contribute(
                $participantConnection->participant,
                $state['plan']['phase']['id'],
                $contribution,
            );
            $triggers->recordSuccess($participantConnection);
        } catch (Throwable $exception) {
            $triggers->recordFailure(
                $participantConnection,
                str($exception->getMessage())->limit(4000)->toString(),
            );

            throw $exception;
        }
    }
}
