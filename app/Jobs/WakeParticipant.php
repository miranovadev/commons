<?php

namespace App\Jobs;

use App\Contracts\ParticipantInference;
use App\Models\ParticipantConnection;
use App\Services\ParticipantActivityService;
use App\Services\ParticipantConnectionTriggerService;
use App\Services\ParticipationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class WakeParticipant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $participantConnectionId,
    ) {
        $this->timeout = max(300, (int) config('participation.wake_timeout', 1800));
        $this->onQueue('participants');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        $participantId = ParticipantConnection::query()->whereKey($this->participantConnectionId)->value('participant_id');

        // A due occurrence already advanced the schedule. Drop a redundant wake
        // while the same participant is running; middleware collisions must not
        // burn three attempts and falsely report an inference failure.
        return [
            (new WithoutOverlapping('participant:'.($participantId ?? $this->participantConnectionId)))->shared()
                ->dontRelease()
                ->expireAfter($this->timeout + 30),
        ];
    }

    public function handle(
        ParticipantInference $inference,
        ParticipationService $participation,
        ParticipantConnectionTriggerService $triggers,
        ParticipantActivityService $activity,
    ): void {
        $participantConnection = ParticipantConnection::query()
            ->with(['connection', 'participant'])
            ->find($this->participantConnectionId);

        if ($participantConnection === null
            || ! $participantConnection->is_enabled) {
            return;
        }

        // Observability outages must not fail an otherwise valid wake.
        $token = rescue(fn () => $activity->begin($participantConnection->participant, $this->timeout), report: true);
        try {
            $state = $participation->prepare($participantConnection->participant);
            if (($state['status'] ?? null) !== 'ready') {
                $reason = data_get($state, 'reason.code', 'participant_not_ready');
                $triggers->recordFailure($participantConnection, "Participant wake skipped: {$reason}.");

                return;
            }

            $cycleId = $state['cycle']['id'];
            $limit = max(1, (int) config('participation.max_actions_per_wake', 64));
            for ($attempt = 0; $attempt < $limit; $attempt++) {
                if ($token !== null) {
                    rescue(fn () => $activity->heartbeat($participantConnection->participant, $token), report: true);
                }
                // Plans are optimistic reads. Only dispatch owns a write transaction;
                // inference never holds locks, and every action gets fresh state.
                if (($state['status'] ?? null) !== 'ready' || $state['cycle']['id'] !== $cycleId) {
                    break;
                }
                if (! $participantConnection->fresh()->is_enabled) {
                    break;
                }

                $contribution = $inference->contribute($participantConnection->connection, $state, [
                    // Transport/logging metadata is separate from participant cognition.
                    'participant_id' => $participantConnection->participant_id,
                    'substrate_id' => $participantConnection->participant->substrate_id,
                ]);
                try {
                    $after = $participation->contribute(
                        $participantConnection->participant,
                        $state['plan']['phase']['id'],
                        $contribution,
                        $state['plan']['schema'],
                    );
                } catch (ConflictHttpException) {
                    // A discarded stale inference consumes an attempt too.
                    // Retry with fresh state, never replay its old payload.
                    $state = $participation->stateFor($participantConnection->participant);

                    continue;
                }
                if (($after['cycle']['id'] ?? null) !== $cycleId || $after['status'] !== 'ready') {
                    break;
                }
                // contribute already returned a fresh post-commit observation.
                $state = $after;
            }
            $triggers->recordSuccess($participantConnection);
        } catch (Throwable $exception) {
            $triggers->recordFailure(
                $participantConnection,
                str($exception->getMessage())->limit(4000)->toString(),
            );

            throw $exception;
        } finally {
            if ($token !== null) {
                rescue(fn () => $activity->end($participantConnection->participant, $token), report: true);
            }
        }
    }
}
