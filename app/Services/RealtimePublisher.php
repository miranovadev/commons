<?php

namespace App\Services;

use App\Events\GraphParticipantChanged;
use App\Events\RealtimeInvalidation;
use App\Models\ContextHorizonMembership;
use Illuminate\Support\Facades\DB;

/** Persistence adapters call this; it contains no eligibility or measurement rules. */
final class RealtimePublisher
{
    public function horizon(string $horizonId, string $substrateId, array $change): void
    {
        $this->afterCommit(fn () => event(new RealtimeInvalidation(
            'context-horizon.'.$horizonId, [...$change, 'context_horizon_id' => $horizonId], $substrateId,
        )));
    }

    public function graphParticipant(string $participantId, string $substrateId): void
    {
        $this->afterCommit(fn () => event(new GraphParticipantChanged($participantId, $substrateId)));
    }

    public function graphMetrics(string $substrateId): void
    {
        $this->afterCommit(fn () => event(new RealtimeInvalidation(
            'substrate.'.$substrateId,
            ['type' => 'graph.metrics_changed', 'substrate_id' => $substrateId],
            $substrateId,
        )));
    }

    public function participant(string $id): void
    {
        $this->afterCommit(fn () => event(new RealtimeInvalidation(
            'participant.'.$id,
            ['type' => 'participant.state_changed', 'participant_id' => $id],
        )));
    }

    public function proposition(string $id, string $type = 'proposition.updated'): void
    {
        $this->afterCommit(function () use ($id, $type): void {
            $membership = ContextHorizonMembership::with('horizon')->find($id);
            if ($membership === null) {
                return;
            }
            event(new RealtimeInvalidation(
                'context-horizon.'.$membership->context_horizon_id,
                ['type' => $type, 'proposition_id' => $id,
                    'context_horizon_id' => $membership->context_horizon_id],
                $membership->horizon->substrate_id,
            ));
        });
    }

    public function horizons(string $substrateId): void
    {
        $this->afterCommit(fn () => event(new RealtimeInvalidation(
            'substrate.'.$substrateId,
            ['type' => 'context-horizons.changed', 'substrate_id' => $substrateId],
            $substrateId,
        )));
    }

    private function afterCommit(callable $publish): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }
        // Register on the surrounding transaction, including nested transactions.
        // Queue/transport outages must not turn a committed action into an HTTP error.
        DB::afterCommit(fn () => rescue($publish, report: true));
    }
}
