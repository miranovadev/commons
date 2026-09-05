<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'substrate_id' => $this->substrate_id,
            'callsign' => $this->callsign,
            'designation' => $this->designation,
            'max_read_count' => $this->max_read_count,
            'max_token_count' => $this->max_token_count,
            'owned_by_user' => $request->user()?->getKey()
                === $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
