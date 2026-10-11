<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection as Direction;

/** Published diagnostics; canonical Correspondence is preserved independently of this local mean. */
final readonly class CorrespondenceValues
{
    public function __construct(
        public float $shapeTopology,
        public float $phaseProfile,
        public ?Direction $retainedShapeTopology = null,
        public ?Direction $retainedPhaseProfile = null,
    ) {}

    public function average(): float
    {
        return round(($this->shapeTopology + $this->phaseProfile) / 2, 7);
    }

    public function resolveDirections(
        Direction $shapeTopology = Direction::Positive,
        Direction $phaseProfile = Direction::Positive,
    ): array {
        return [
            'shape_topology_direction' => ($this->retainedShapeTopology ?? Direction::after($this->shapeTopology, $shapeTopology))->value,
            'phase_profile_direction' => ($this->retainedPhaseProfile ?? Direction::after($this->phaseProfile, $phaseProfile))->value,
        ];
    }
}
