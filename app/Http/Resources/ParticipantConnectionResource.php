<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'participant_id' => $this->participant_id,
            'connection_id' => $this->connection_id,
            'connection' => $this->whenLoaded(
                'connection',
                fn () => new ConnectionResource($this->connection),
            ),
            'is_enabled' => $this->is_enabled,
            'configuration' => $this->configuration,
            'next_trigger_at' => $this->next_trigger_at,
            'last_triggered_at' => $this->last_triggered_at,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
