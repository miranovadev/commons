<?php

namespace App\Http\Resources;

use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\ReinforcementService;
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
        $parent = $this->relationLoaded('parents')
            ? $this->parents->first()
            : null;

        return [
            'id' => $this->getKey(),
            'reinforcement_count' => $this->reinforcement_count ?? app(ReinforcementService::class)->count($this->resource),
            'attestation_summary' => $this->when($this->attestation_summary !== null, $this->attestation_summary),
            'instruments' => $this->whenLoaded('instrumentExecutions', fn () => $this->instrumentExecutions->map(fn ($execution) => [
                'id' => $execution->id, 'kind' => $execution->kind,
                'reference' => $execution->reference, 'status' => $execution->status,
                'failure_reason' => $execution->failure_reason, 'receipt_id' => $execution->receipt?->id,
                'reinforcement_status' => null, // Loaded state is viewer-relative; supplied by Participant plans.
                'content_verified' => $execution->receipt?->metadata['content_verified'] ?? false,
            ])),
            'attestation_counts' => $this->whenLoaded('attestationCounts', fn () => $this->attestationCounts->all()),
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
            'transition' => $parent === null ? null : [
                'type' => $parent->pivot->type,
                'from' => $parent->register,
                'to' => $this->register,
                'source_id' => $parent->getKey(),
            ],
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
            // Last persisted values remain visible; observation never repairs them.
            'observables' => [
                'status' => $measurement === null ? 'missing'
                    : ($measurement->calculator_version === LineageMeasurementService::VERSION ? 'current' : 'stale'),
                'correspondence' => $measurement?->correspondence,
                'chirality' => $measurement?->chirality,
                'closure' => $measurement?->closure,
                'calculated_at' => $measurement?->calculated_at,
            ],
            'context_horizon' => $horizon === null ? null : [
                'id' => $horizon->id,
                'sequence' => $horizon->sequence,
                'parent_context_horizon_id' => $horizon->parent_context_horizon_id,
                'opened_by_proposition_id' => $horizon->opened_by_proposition_id,
                'domain' => $horizon->domain->value,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
