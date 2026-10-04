<?php

namespace Tests\Unit;

use App\Domain\Observables\ChiralityValues;
use App\Enums\ObservableDirection as Direction;
use App\Services\Observables\LocalChiralityCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalChiralityCalculatorTest extends TestCase
{
    public static function displacements(): array
    {
        return ['refute to support' => [1, -1, 1.0], 'support to refute' => [-1, 1, -1.0],
            'unchanged support' => [1, 1, 0.0], 'unchanged refute' => [-1, -1, 0.0],
            'neutral Origin to support' => [1, 0, .5], 'neutral Origin to refute' => [-1, 0, -.5]];
    }

    #[DataProvider('displacements')]
    public function test_signed_displacement(float $current, float $previous, float $expected): void
    {
        $this->assertSame($expected, (new LocalChiralityCalculator)->orientation($current, $previous));
    }

    public static function rhythms(): array
    {
        return ['empty' => [[], false, null, 0.0], 'singleton' => [[5], false, 0.0, 0.0],
            'singleton zero' => [[0], false, null, 0.0], 'regular' => [[5, 5], true, 0.0, 1.0],
            'unequal' => [[1, 3], true, .5, .3333333], 'mixed zeros' => [[0, 4], true, 1.0, 0.0],
            'irregular negative' => [[0, 0, 6], true, 1.4142135623730951, -.1715729]];
    }

    #[DataProvider('rhythms')]
    public function test_developmental_population_cv(array $intervals, bool $mature, ?float $cv, float $value): void
    {
        $result = (new LocalChiralityCalculator)->cadence($intervals);
        $this->assertSame($mature, $result['mature']);
        $this->assertEqualsWithDelta($value, $result['value'], 1e-7);
        if ($cv === null) {
            $this->assertNull($result['cv']);
        } else {
            $this->assertEqualsWithDelta($cv, $result['cv'], 1e-12);
        }
    }

    public function test_mature_all_zero_intervals_are_regular_but_raw_cv_remains_undefined(): void
    {
        foreach ([[0, 0], [0, 0, 0]] as $intervals) {
            $result = (new LocalChiralityCalculator)->cadence($intervals);
            $this->assertTrue($result['mature']);
            $this->assertTrue($result['zero_mean_regular']);
            $this->assertNull($result['raw_cv']);
            $this->assertSame(0.0, $result['cv']);
            $this->assertSame(1.0, $result['value']);
        }
    }

    public function test_zero_retains_component_directions(): void
    {
        $this->assertSame(['orientation_direction' => 'positive', 'attestation_cadence_direction' => 'negative'],
            (new ChiralityValues(0, 0))->resolveDirections(Direction::Positive, Direction::Negative));
        $this->assertSame(['orientation_direction' => 'negative', 'attestation_cadence_direction' => 'positive'],
            (new ChiralityValues(0, 0))->resolveDirections());
    }
}
