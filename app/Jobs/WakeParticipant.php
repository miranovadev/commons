<?php

namespace App\Jobs;

use App\Contracts\ParticipantInference;
use App\Enums\WakeTermination;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\ParticipantConnection;
use App\Models\Phase;
use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\ParticipantActivityService;
use App\Services\ParticipantConnectionTriggerService;
use App\Services\ParticipantContextSerializer;
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
            $wake = WakeConfiguration::resolve($participantConnection->configuration)['wake'];
            $termination = WakeTermination::from($wake['termination']);
            $untilBirth = $termination === WakeTermination::PropositionCreated;
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
                if ((! $untilBirth && Cycle::find($observedCycleId)?->rested_at !== null)
                    || ($termination === WakeTermination::Phase && Phase::find($phaseId)?->completed_at !== null)
                    || ($termination === WakeTermination::Cycle && Cycle::find($cycleId)?->completed_at !== null)) {
                    break;
                }
                if ($token !== null) {
                    rescue(fn () => $activity->heartbeat($participantConnection->participant, $token), report: true);
                }
                // Reload the whole authoritative plan before each decision, including
                // conflict retries and the first Phase of a newly prepared Cycle.
                $participant = $participantConnection->participant->fresh();
                $state = $participation->stateFor($participant, $fieldVersion);
                if (($state['status'] ?? null) !== 'ready') {
                    $reason = data_get($state, 'reason.code', 'participant_not_ready');
                    if ($untilBirth && $reason === 'recovery_unavailable') {
                        break;
                    }
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
                $plan = $state['plan'];
                $automatic = $this->forcedMove($plan, $wake['skip_inference_for']);
                $contribution = $automatic ?? $this->inferContribution($inference, $currentConnection->connection, $participant, $state);
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
                        $plan['schema'],
                        expectedFieldVersion: $automatic !== null ? $fieldVersion : null,
                        createdProposition: $createdProposition,
                    );
                } catch (ConflictHttpException) {
                    // Discard the stale choice. The next iteration rebuilds the plan;
                    // the same whole-job deadline bounds repeated conflicts.
                    continue;
                }
                if ($untilBirth && $createdProposition) {
                    break;
                }
                // contribute may already have prepared a successor Cycle, including
                // recovery after Rest. Inspect the Cycle that actually acted.
                if ((! $untilBirth && Cycle::findOrFail($actingCycleId)->rested_at !== null)
                    || ($termination === WakeTermination::Phase && Phase::findOrFail($phaseId)->completed_at !== null)
                    || ($termination === WakeTermination::Cycle && Cycle::findOrFail($cycleId)->completed_at !== null)) {
                    break;
                }
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

    private function forcedMove(array $plan, array $enabled): ?array
    {
        $schema = $plan['schema'];
        $actions = $schema['$defs']['actions'] ?? null;
        if (! is_array($actions) || count($actions) !== 1 || isset($plan['preparation'])
            || in_array($plan['acquisition']['status'] ?? null, ['pending', 'awaiting_resolution'], true)) {
            return null;
        }

        $name = array_key_first($actions);
        $properties = $actions[$name]['properties'] ?? [];
        if (! in_array($name, $enabled, true) || ($properties['action']['const'] ?? null) !== $name
            || ($schema['oneOf'] ?? null) !== [['$ref' => '#/$defs/actions/'.$name]]) {
            return null;
        }
        if ($name === 'participant.rest' && array_keys($properties) === ['action']) {
            return ['action' => $name];
        }

        // Optional Receipt loading/unloading is also a Participant decision.
        $targets = $properties['proposition_id']['enum'] ?? [];
        if ($name === 'proposition.traverse' && count($properties) === 2
            && is_array($targets) && count($targets) === 1 && is_string($targets[0] ?? null)) {
            return ['action' => $name, 'proposition_id' => $targets[0]];
        }

        return null;
    }

    private function inferContribution(ParticipantInference $inference, Connection $connection, Participant $participant, array $state): array
    {
        $state = app(ParticipantContextSerializer::class)->participation($state);
        $preparedExecution = null;
        $delivery = null;
        if ($executionId = data_get($state, 'preparation.execution_id')) {
            $preparedExecution = EvidenceExecution::findOrFail($executionId);
            $delivery = app(PreparationService::class)->delivery($participant, $preparedExecution, 'inline-text');
            $state['prepared_context'] = array_diff_key($delivery, ['presentation_token' => true]);
        }
        try {
            $contribution = $inference->contribute($connection, $state, [
                'participant_id' => $participant->id,
                'substrate_id' => $participant->substrate_id,
            ]);
        } catch (Throwable $exception) {
            if ($preparedExecution !== null) {
                // Only trusted inference failure is evidence. A later rejected
                // contribution, stale action or invalid acknowledgement is not.
                app(PreparationService::class)->fail($participant, $preparedExecution,
                    ArtifactReaderRegistry::METHOD, ArtifactReaderRegistry::VERSION, 'inference',
                    'The prepared inference interaction could not complete. No new presentation was acknowledged.');
            }
            throw $exception;
        }
        if ($preparedExecution !== null) {
            // A successful inference call received this exact transient context.
            // Extraction alone and failed inference never acknowledge presentation.
            app(PreparationService::class)->acknowledge($participant, $preparedExecution, $delivery['presentation_token']);
        }

        return $contribution;
    }
}
