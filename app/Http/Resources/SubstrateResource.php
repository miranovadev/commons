<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubstrateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'visibility' => $this->visibility,
            'configuration' => [
                ...($this->configuration ?? []),
                'presentation' => \App\Support\SubstratePresentation::resolve($this->configuration),
            ],
            'can_update' => \Illuminate\Support\Facades\Gate::allows('update', $this->resource),
            'participants_count' => $this->whenCounted('participants'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
