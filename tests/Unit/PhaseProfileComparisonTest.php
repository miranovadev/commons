<?php

namespace Tests\Unit;

use App\Domain\Observables\PhaseProfile;
use App\Services\Observables\PhaseProfileComparisonCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhaseProfileComparisonTest extends TestCase
{
    public static function similarities(): array
    {
        return [[null, null, 0.0], [null, 0, 0.0], [0, null, 0.0], [null, 4, 0.0],
            [0, 0, 1.0], [0, 3, -1.0], [3, 0, -1.0], [4, 4, 1.0], [2, 4, 0.0], [1, 4, -.5]];
    }

    #[DataProvider('similarities')]
    public function test_bounded_symmetric_kernel_distinguishes_null_and_zero(?float $a, ?float $b, float $expected): void
    {
        $calc = new PhaseProfileComparisonCalculator;
        $p = new PhaseProfile(array_fill(0, 7, $a));
        $q = new PhaseProfile(array_fill(0, 7, $b));
        $this->assertSame(array_fill(0, 7, $expected), $calc->contributions($p, $q));
        $this->assertSame($expected, $calc->calculate($p, $q));
        $this->assertSame($expected, $calc->calculate($q, $p));
    }

    public function test_all_seven_slots_retain_weight_when_only_one_is_available(): void
    {
        $calc = new PhaseProfileComparisonCalculator;
        $p = new PhaseProfile([null, 0, null, null, null, null, null]);
        $this->assertSame([0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0], $calc->contributions($p, PhaseProfile::origin()));
        $this->assertEqualsWithDelta(1 / 7, $calc->calculate($p, PhaseProfile::origin()), 1e-12);
        $this->assertSame(0.0, $calc->calculate(new PhaseProfile(array_fill(0, 7, null)), PhaseProfile::origin()));
    }

    public function test_accepted_bounded_comparison_math_from_historical_diagnostic(): void
    {
        $a = new PhaseProfile([2, 2 / 5, 31 / 13, 0, 47 / 23, 35 / 9, 7 / 4]);
        $b = new PhaseProfile([5 / 4, 1 / 3, 44 / 13, 0, 22 / 15, 34 / 11, 7 / 4]);
        $calc = new PhaseProfileComparisonCalculator;
        $this->assertSame(1.0, $calc->calculate($a, $a));
        $this->assertEqualsWithDelta(.621546994, $calc->calculate($a, $b), 1e-9);
    }

    public static function invalidProfiles(): array
    {
        return [[[1]], [[-1, 0, 0, 0, 0, 0, 0]], [[INF, 0, 0, 0, 0, 0, 0]],
            [[NAN, 0, 0, 0, 0, 0, 0]], [['1', 0, 0, 0, 0, 0, 0]], [[1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0]]];
    }

    #[DataProvider('invalidProfiles')]
    public function test_invalid_profiles_are_rejected(array $values): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PhaseProfile($values);
    }
}
