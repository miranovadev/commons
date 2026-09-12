<?php

namespace App\Events;

use App\Models\Substrate;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** Identifier-only hints. The HTTP API remains authoritative. */
final class RealtimeInvalidation implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(
        private readonly string $channel,
        private readonly array $payload,
        private readonly ?string $publicSubstrateId = null,
    ) {}

    public function broadcastOn(): array
    {
        // Check visibility when the queue actually delivers the event.
        // A guessed public channel must never expose private substrate activity.
        $public = $this->publicSubstrateId !== null
            && Substrate::find($this->publicSubstrateId)?->isPublic();

        return [new PrivateChannel($this->channel), ...($public
            ? [new Channel('public.'.$this->channel)] : [])];
    }

    public function broadcastAs(): string
    {
        return 'invalidation';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
