<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection as Direction;

/** Local diagnostics; only their mean participates in structural attenuation. */
final readonly class CorrespondenceValues
{
    public function __construct(
        public float $shapeTopology,
        public float $cardinalPosition,
    ) {}

    public function average(): float
    {
        return round(($this->shapeTopology + $this->cardinalPosition) / 2, 7);
    }

    public function resolveDirections(
        Direction $shapeTopology = Direction::Positive,
        Direction $cardinalPosition = Direction::Positive,
    ): array {
        return [
            'shape_topology_direction' => Direction::after($this->shapeTopology, $shapeTopology)->value,
            'cardinal_position_direction' => Direction::after($this->cardinalPosition, $cardinalPosition)->value,
        ];
    }
}
