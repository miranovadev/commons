<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;

/** Existing-Gate Write coherence only; never evaluates a prospective result. */
final readonly class ChiralityGateService
{
    public function __construct(private PropositionMeasurementService $measurements) {}

    public function evaluate(Proposition $gate, ?ParticipationContext $context = null): array
    {
        $gate = $context?->proposition($gate) ?? $gate;
        $this->measurements->observeMissing(collect([$gate]), $context);
        $measurement = $gate->measurement;
        $orientation = $measurement?->orientation_direction;
        $cadence = $measurement?->attestation_cadence_direction;
        $available = $orientation !== null && $cadence !== null;
        $admissible = $available && $orientation === $cadence;

        return [
            'gate_id' => $gate->id,
            'admissible' => $admissible,
            'reason' => $admissible ? null : ($available ? 'component_directions_disagree' : 'component_directions_unavailable'),
            'orientation_direction' => $orientation,
            'attestation_cadence_direction' => $cadence,
            // Numeric member direction, as resolved by Homeostatic selection; not evidence lean.
            'chirality_direction' => $measurement?->chirality === null ? null
                : ObservableDirection::after($measurement->chirality, ObservableDirection::defaultFor('chirality'))->value,
        ];
    }
}
