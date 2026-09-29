<?php

namespace Tests\Unit;

use App\Domain\Observables\ChiralityValues;
use App\Enums\ObservableDirection as Direction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChiralityValuesTest extends TestCase
{
    public static function populations(): array
    {
        return [[0, 0, 0, 0, 0.0], [0, 1, 0, 0, -1.0], [0, 0, 0, 1, -1.0],
            [0, 5, 0, 1, 1.0], [2, 5, 0, 1, -1.0], [6, 3, 8, 4, 1.0],
            [0, 4, 8, 4, -1.0], [1, 1, 2, 1, 0.0]];
    }

    #[DataProvider('populations')]
    public function test_population_existence_and_rate_similarity(int $a, int $n, int $b, int $m, float $expected): void
    {
        $this->assertSame($expected, ChiralityValues::similarity($a, $n, $b, $m));
    }

    public function test_composition_and_independent_hysteresis_have_no_admissibility_rule(): void
    {
        foreach ([[.8, .6, .7], [-.8, .8, 0.0], [.8, 0, .4]] as [$orientation, $cadence, $expected]) {
            $this->assertSame($expected, (new ChiralityValues($orientation, $cadence))->average());
        }
        $this->assertSame(['orientation_direction' => 'negative', 'attestation_cadence_direction' => 'positive'],
            (new ChiralityValues(0, 0))->resolveDirections(Direction::Negative, Direction::Positive));
        $this->assertSame(['orientation_direction' => 'negative', 'attestation_cadence_direction' => 'negative'],
            (new ChiralityValues(0, -1))->resolveDirections());
    }
}
