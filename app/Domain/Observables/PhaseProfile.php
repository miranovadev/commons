<?php

namespace App\Domain\Observables;

use App\Enums\PhaseKind;
use InvalidArgumentException;

/** Seven Phase-local means; null is unavailable, numeric zero is observed. */
final readonly class PhaseProfile
{
    /** @var list<?float> */
    public array $values;

    /** @param list<int|float|null> $values */
    public function __construct(array $values)
    {
        if (! array_is_list($values) || count($values) !== 7) {
            throw new InvalidArgumentException('A Phase Profile requires seven canonical Phase slots.');
        }
        foreach ($values as $value) {
            if ($value !== null && ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0)) {
                throw new InvalidArgumentException('Phase means must be finite non-negative numbers or null.');
            }
        }
        $this->values = array_map(fn ($value) => $value === null ? null : (float) $value, $values);
    }

    /** Constitutional reference, never fabricated empirical observations. */
    public static function origin(): self
    {
        return new self([0, 0, 0, 0, 0, 0, 1]);
    }

    public function component(PhaseKind $phase): ?float
    {
        return $this->values[$phase->value - 1];
    }
}
