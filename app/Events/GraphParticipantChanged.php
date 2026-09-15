<?php

namespace App\Events;

use App\Models\Substrate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** Immediate hints so a busy participation queue cannot postpone activity until after a wake. */
final class GraphParticipantChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public readonly string $participantId, public readonly string $substrateId) {}

    public function broadcastOn(): array
    {
        $name = 'substrate.'.$this->substrateId;

        return [new PrivateChannel($name), ...(Substrate::find($this->substrateId)?->isPublic()
            ? [new Channel('public.'.$name)] : [])];
    }

    public function broadcastAs(): string
    {
        return 'invalidation';
    }

    public function broadcastWith(): array
    {
        return ['type' => 'graph.participant_changed', 'participant_id' => $this->participantId, 'substrate_id' => $this->substrateId];
    }
}
