<?php

namespace App\Jobs;

use App\Contracts\ParticipantInference;
use App\Enums\WakeTermination;
use App\Models\Cycle;
use App\Models\EvidenceExecution;
use App\Models\ParticipantConnection;
use App\Models\Phase;
use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\ParticipantActivityService;
use App\Services\ParticipantConnectionTriggerService;
use App\Services\ParticipationService;
use App\Services\PreparationService;
use App\Services\WakeClock;
use App\Support\WakeConfiguration;
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

    // A worker-enforced timeout must not restart an unbounded Rest wake.
    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $participantConnectionId,
    ) {
        $configuration = ParticipantConnection::find($participantConnectionId)?->configuration;
        $this->timeout = max(1, min(WakeConfiguration::MAX_TIMEOUT_SECONDS,
            (int) WakeConfiguration::resolve($configuration)['wake']['timeout_seconds']));
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
        WakeClock $clock,
    ): void {
        $deadline = $clock->now() + $this->timeout;
        $participantConnection = ParticipantConnection::query()
            ->with(['connection', 'participant'])
            ->find($this->participantConnectionId);

        if ($participantConnection === null
            || ! $participantConnection->is_enabled) {
            return;
        }

        // Observability outages must not fail an otherwise valid wake.
        $token = rescue(fn () => $activity->begin($participantConnection->participant, $this->timeout), report: true);
        $preparedExecution = null;
        try {
            $state = $participation->prepare($participantConnection->participant);
            if (($state['status'] ?? null) !== 'ready') {
                $reason = data_get($state, 'reason.code', 'participant_not_ready');
                $triggers->recordFailure($participantConnection, "Participant wake skipped: {$reason}.");

                return;
            }

            $cycleId = $state['cycle']['id'];
            $phaseId = $state['plan']['phase']['id'];
            $observedCycleId = $cycleId;
            $termination = WakeTermination::from(
                WakeConfiguration::resolve($participantConnection->configuration)['wake']['termination'],
            );
            while (true) {
                if ($clock->now() >= $deadline) {
                    $triggers->recordFailure($participantConnection, 'Participant wake timed out; completed contributions are preserved.');

                    return;
                }
                $currentConnection = $participantConnection->fresh();
                if ($currentConnection === null || ! $currentConnection->is_enabled) {
                    return;
                }
                // A human or another writer may have advanced the original boundary.
                if (Cycle::find($observedCycleId)?->rested_at !== null
                    || ($termination === WakeTermination::Phase && Phase::find($phaseId)?->completed_at !== null)
                    || ($termination === WakeTermination::Cycle && Cycle::find($cycleId)?->completed_at !== null)) {
                    break;
                }
                if ($token !== null) {
                    rescue(fn () => $activity->heartbeat($participantConnection->participant, $token), report: true);
                }
                // Reload the whole authoritative plan before EVERY inference, including
                // conflict retries and the first Phase of a newly prepared Cycle.
                $participant = $participantConnection->participant->fresh();
                $state = $participation->stateFor($participant);
                if (($state['status'] ?? null) !== 'ready') {
                    $reason = data_get($state, 'reason.code', 'participant_not_ready');
                    $triggers->recordFailure($participantConnection, "Participant wake skipped: {$reason}.");

                    return;
                }
                if ($clock->now() >= $deadline) {
                    $triggers->recordFailure($participantConnection, 'Participant wake timed out; completed contributions are preserved.');

                    return;
                }
                if (($termination === WakeTermination::Phase && $state['plan']['phase']['id'] !== $phaseId)
                    || ($termination === WakeTermination::Cycle && $state['cycle']['id'] !== $cycleId)) {
                    break;
                }
                $actingCycleId = $state['cycle']['id'];
                $observedCycleId = $actingCycleId;
                $preparedExecution = null;
                $delivery = null;
                if ($executionId = data_get($state, 'plan.preparation.execution_id')) {
                    $preparedExecution = EvidenceExecution::findOrFail($executionId);
                    $delivery = app(PreparationService::class)->delivery($participant, $preparedExecution, 'inline-text');
                    $state['prepared_context'] = array_diff_key($delivery, ['presentation_token' => true]);
                }
                $contribution = $inference->contribute($currentConnection->connection, $state, [
                    'participant_id' => $participant->id,
                    'substrate_id' => $participant->substrate_id,
                ]);
                if ($preparedExecution !== null) {
                    // A successful inference call received this exact transient context.
                    // Extraction alone and failed inference never acknowledge presentation.
                    app(PreparationService::class)->acknowledge($participant, $preparedExecution, $delivery['presentation_token']);
                }
                // Preserve the separate transport timeout, but never start a new
                // contribution after an inference has consumed the whole deadline.
                if ($clock->now() >= $deadline) {
                    $triggers->recordFailure($participantConnection, 'Participant wake timed out; completed contributions are preserved.');

                    return;
                }
                try {
                    $participation->contribute(
                        $participant,
                        $state['plan']['phase']['id'],
                        $contribution,
                        $state['plan']['schema'],
                    );
                } catch (ConflictHttpException) {
                    // Discard stale inference. The next iteration rebuilds the plan;
                    // the same whole-job deadline bounds repeated conflicts.
                    continue;
                }
                // contribute may already have prepared a successor Cycle, including
                // recovery after Rest. Inspect the Cycle that actually acted.
                if (Cycle::findOrFail($actingCycleId)->rested_at !== null
                    || ($termination === WakeTermination::Phase && Phase::findOrFail($phaseId)->completed_at !== null)
                    || ($termination === WakeTermination::Cycle && Cycle::findOrFail($cycleId)->completed_at !== null)) {
                    break;
                }
            }
            $triggers->recordSuccess($participantConnection);
        } catch (Throwable $exception) {
            if ($preparedExecution !== null) {
                rescue(fn () => app(PreparationService::class)->fail($participantConnection->participant,
                    $preparedExecution, ArtifactReaderRegistry::METHOD,
                    ArtifactReaderRegistry::VERSION, 'inference',
                    'Prepared interaction failed: '.mb_substr($exception->getMessage(), 0, 900)), report: true);
            }
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
