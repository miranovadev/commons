<?php

namespace App\Domain\Observables;

use App\Enums\ObservableDirection as Direction;

/** Published diagnostics; canonical Correspondence is preserved independently of this local mean. */
final readonly class CorrespondenceValues
{
    public function __construct(
        public float $shapeTopology,
        public float $cardinalPosition,
        public ?Direction $retainedShapeTopology = null,
        public ?Direction $retainedCardinalPosition = null,
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
            'shape_topology_direction' => ($this->retainedShapeTopology ?? Direction::after($this->shapeTopology, $shapeTopology))->value,
            'cardinal_position_direction' => ($this->retainedCardinalPosition ?? Direction::after($this->cardinalPosition, $cardinalPosition))->value,
        ];
    }
}
