<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cycle = $this->relationLoaded('latestCycle')
            ? $this->latestCycle
            : null;
        $phase = $cycle?->relationLoaded('currentPhase')
            ? $cycle->currentPhase
            : null;
        $gate = $phase?->relationLoaded('gate')
            ? $phase->gate
            : null;

        return [
            'id' => $this->getKey(),
            'substrate_id' => $this->substrate_id,
            'callsign' => $this->callsign,
            'designation' => $this->designation,
            'max_read_count' => $this->max_read_count,
            'max_token_count' => $this->max_token_count,
            'owned_by_user' => $request->user()?->getKey()
                === $this->user_id,
            'position' => $cycle === null ? null : [
                'cycle_sequence' => $cycle->sequence,
                'cycle_completed' => $cycle->completed_at !== null,
                'phase' => $phase === null ? null : [
                    'number' => $phase->kind->value,
                    'name' => str($phase->kind->name)->headline()->lower()->toString(),
                    'write_register' => $phase->write_register,
                ],
                'gate' => $gate === null ? null : [
                    'id' => $gate->getKey(),
                    'register' => $gate->register,
                    'tier' => $gate->tier,
                    'title' => str($gate->statement)
                        ->before("\n")
                        ->trim()
                        ->limit(72)
                        ->toString(),
                ],
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
