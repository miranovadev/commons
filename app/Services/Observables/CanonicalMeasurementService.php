<?php

namespace App\Services\Observables;

use App\Models\CanonicalMeasurement;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Services\Participation\ParticipationContext;

/** Prospective preservation only; historical state is never reconstructed. */
class CanonicalMeasurementService
{
    public function for(Proposition $position, ?ParticipationContext $context = null): ?CanonicalMeasurement
    {
        return $context === null || ! $context->propositions->has($position->id)
            ? CanonicalMeasurement::find($position->id)
            : $context->remember('measurements:canonical', fn () => CanonicalMeasurement::whereIn('proposition_id', $context->propositions->keys())->get()->keyBy('proposition_id'))->get($position->id);
    }

    public function preserve(Proposition $target, Proposition $source, PropositionMeasurement $resolved, string $correspondenceDirection): void
    {
        if ($target->tier !== 'committal' || $source->tier !== 'expressive' || $resolved->proposition_id !== $source->id) {
            throw new \DomainException('Canonical preservation requires the selected expressive source measurement.');
        }
        $attributes = $resolved->only(['correspondence', 'chirality', 'chirality_direction', 'orientation', 'attestation_cadence',
            'orientation_direction', 'attestation_cadence_direction', 'shape_topology', 'cardinal_position',
            'shape_topology_direction', 'cardinal_position_direction']);
        if (count($attributes) !== 11 || in_array(null, $attributes, true)) {
            throw new \DomainException('Canonical preservation requires complete resolved source metadata.');
        }
        (new CanonicalMeasurement)->forceFill(['proposition_id' => $target->id, 'source_proposition_id' => $source->id,
            'correspondence_direction' => $correspondenceDirection, ...$attributes])->save();
    }
}
