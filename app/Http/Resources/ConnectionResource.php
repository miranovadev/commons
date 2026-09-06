<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'provider' => $this->provider->value,
            'name' => $this->name,
            'configuration' => $this->configuration,
            'has_credentials' => $this->credentials !== null,
            'participants_count' => $this->whenCounted('participantConnections'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
