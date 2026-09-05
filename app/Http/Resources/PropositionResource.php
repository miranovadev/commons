<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PropositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstLine = str($this->statement)->before("\n")->trim()->toString();
        $measurement = $this->relationLoaded('measurement')
            ? $this->measurement
            : null;
        $horizon = $this->relationLoaded('contextHorizonMembership')
            ? $this->contextHorizonMembership?->horizon
            : null;

        return [
            'id' => $this->getKey(),
            'title' => Str::limit($firstLine, 72),
            'statement' => $this->statement,
            'register' => $this->register,
            'tier' => $this->tier,
            'participant' => $this->whenLoaded(
                'phase',
                fn () => $this->phase->cycle->participant->only([
                    'id',
                    'callsign',
                ]),
            ),
            'parent_ids' => $this->whenLoaded(
                'parents',
                fn () => $this->parents->modelKeys(),
            ),
            'children' => $this->whenLoaded(
                'children',
                fn () => $this->children->map(fn ($child): array => [
                    'id' => $child->getKey(),
                    'type' => $child->pivot->type,
                ])->values(),
            ),
            'is_origin' => $this->whenLoaded(
                'parents',
                fn () => $this->tier === 'committal'
                    && $this->parents->isEmpty(),
            ),
            'observables' => [
                'correspondence' => $measurement?->correspondence,
                'chirality' => $measurement?->chirality,
                'closure' => $measurement?->closure,
                'calculated_at' => $measurement?->calculated_at,
            ],
            'context_horizon' => $horizon === null ? null : [
                'sequence' => $horizon->sequence,
                'domain' => $horizon->domain->value,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
