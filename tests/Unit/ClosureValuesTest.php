<?php

namespace Tests\Unit;

use App\Domain\Observables\ClosureValues;
use App\Enums\ObservableDirection as Direction;
use App\Services\Observables\ClosureCalculator;
use PHPUnit\Framework\TestCase;

class ClosureValuesTest extends TestCase
{
    public function test_alignment_and_zero_retention_are_independent_of_the_average(): void
    {
        $this->assertTrue((new ClosureValues(-1, -1))->resolve()['admissible']);
        $this->assertTrue((new ClosureValues(1, 0.2))->resolve()['admissible']);
        $this->assertFalse((new ClosureValues(-0.2, 1))->resolve()['admissible']);
        $this->assertSame(0.4, (new ClosureValues(-0.2, 1))->average());
        $this->assertFalse((new ClosureValues(0, 0))->resolve(Direction::Negative, Direction::Positive)['admissible']);
        $this->assertTrue((new ClosureValues(0, 1))->resolve(Direction::Positive, Direction::Negative)['admissible']);
        $this->assertSame('negative', (new ClosureValues(0, 0))->resolve()['depth_direction']);
    }

    public function test_frozen_historical_closure_is_preserved_even_when_it_differs_from_the_pure_mean(): void
    {
        $this->assertSame(-0.7, (new ClosureValues(-0.8, -0.6))->average());
        $this->assertSame(0.25, (new ClosureValues(-0.8, -0.6, 0.25))->average());
    }

    public function test_population_cv_is_scale_invariant_and_zero_variability_is_explicit(): void
    {
        $calc = ClosureCalculator::class;
        $this->assertEqualsWithDelta(1 / 3, $calc::coefficientOfVariation([5, 10]), 1e-12);
        $this->assertSame($calc::coefficientOfVariation([5, 10]), $calc::coefficientOfVariation([50, 100]));
        $this->assertSame(1.0, $calc::variabilitySimilarity(0, 0));
        $this->assertSame(-1.0, $calc::variabilitySimilarity(0, .5));
        $this->assertSame(1.0, $calc::variabilitySimilarity(1 / 3, 1 / 3));
    }

    public function test_lateral_centroids_are_positive_real_values_with_bounded_similarity(): void
    {
        $calc = ClosureCalculator::class;
        $history = [1, 2, 2, 3];
        $centroid = array_sum($history) / count($history);
        $this->assertEquals(2.0, $centroid);
        $this->assertSame(1.0, $calc::positionalSimilarity(2, $centroid));
        $this->assertSame(0.3333333, $calc::positionalSimilarity(4, $centroid));
        $this->assertSame(0.6, $calc::positionalSimilarity(1, 1.5));
        $this->assertGreaterThanOrEqual(-1.0, $calc::positionalSimilarity(1000000, 1.5));
        $this->assertLessThanOrEqual(1.0, $calc::positionalSimilarity(1000000, 1.5));
    }

    public function test_invalid_component_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ClosureValues(NAN, 0))->resolve();
    }
}
