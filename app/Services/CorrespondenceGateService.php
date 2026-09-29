<?php

namespace App\Services;

use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;

/** Existing-Gate Write coherence only; never evaluates a prospective result. */
final readonly class CorrespondenceGateService
{
    public function __construct(private PropositionMeasurementService $measurements) {}

    public function evaluate(Proposition $gate, ?ParticipationContext $context = null): array
    {
        $gate = $context?->proposition($gate) ?? $gate;
        $this->measurements->observeMissing(collect([$gate]), $context);
        $measurement = $gate->measurement;
        $shape = $measurement?->shape_topology_direction;
        $cardinal = $measurement?->cardinal_position_direction;
        $available = $shape !== null && $cardinal !== null;
        $admissible = $available && $shape === $cardinal;

        return [
            'gate_id' => $gate->id,
            'admissible' => $admissible,
            'reason' => $admissible ? null : ($available ? 'component_directions_disagree' : 'component_directions_unavailable'),
            'shape_topology_direction' => $shape,
            'cardinal_position_direction' => $cardinal,
            'correspondence_direction' => $this->measurements->correspondenceDirectionFor($gate, $context)->value,
        ];
    }
}
