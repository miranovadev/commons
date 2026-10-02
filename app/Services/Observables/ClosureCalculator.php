<?php

namespace App\Services\Observables;

use App\Domain\Observables\ClosureValues;
use App\Domain\Observables\LineageComparison;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;

final readonly class ClosureCalculator
{
    public function __construct(private ClosureBirthService $births) {}

    public function localComponents(Proposition $proposition, LineageComparison $comparison): ClosureValues
    {
        return $this->components($proposition, $comparison->current->context);
    }

    public function components(Proposition $position, ?ParticipationContext $context = null): ClosureValues
    {
        $observation = $position->exists ? $this->births->stored($position, $context) : $this->births->prospective($position);

        return new ClosureValues($observation['depth'], $observation['cadence'], $observation['closure'], $observation);
    }

    public static function similarity(int $left, int $right): float
    {
        if ($left < 1 || $right < 1) {
            throw new DomainException('Measured committed depths must be positive.');
        }

        return self::bounded(1 - 2 * abs($left - $right) / max($left, $right));
    }

    public static function positionalSimilarity(float $left, float $right): float
    {
        if (! is_finite($left) || ! is_finite($right) || $left <= 0 || $right <= 0) {
            throw new DomainException('Committed positions and centroids must be positive real values.');
        }

        return self::bounded(1 - 2 * abs($left - $right) / ($left + $right));
    }

    public static function coefficientOfVariation(array $intervals): float
    {
        if (count($intervals) < 2 || min($intervals) <= 0) {
            throw new DomainException('CV requires at least two positive intervals.');
        }
        $mean = array_sum($intervals) / count($intervals);
        $variance = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $intervals)) / count($intervals);

        return sqrt($variance) / $mean;
    }

    public static function variabilitySimilarity(float $left, float $right): float
    {
        if ($left < 0 || $right < 0 || ! is_finite($left) || ! is_finite($right)) {
            throw new DomainException('CV must be finite and nonnegative.');
        }

        return $left == 0 && $right == 0 ? 1.0 : self::bounded(1 - 2 * abs($left - $right) / max($left, $right));
    }

    private static function bounded(float $value): float
    {
        return round(max(-1, min(1, $value)), 7);
    }
}
