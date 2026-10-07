<?php

namespace Tests\Unit;

use App\Domain\Observables\PhaseProfile;
use App\Enums\PhaseKind;
use App\Services\Observables\PhaseProfileComparisonCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhaseProfileComparisonTest extends TestCase
{
    public function test_origin_has_seven_real_canonical_values(): void
    {
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0], PhaseProfile::origin()->values);
        $this->assertSame(1.0, PhaseProfile::origin()->component(PhaseKind::Order));
    }

    public static function comparisons(): array
    {
        return [[0, 0, 1], [0, 3, -1], [3, 0, -1], [4, 4, 1], [2, 4, 0], [1, 4, -.5],
            [null, 0, 0], [0, null, 0], [null, null, 0], [null, 4, 0]];
    }

    #[DataProvider('comparisons')]
    public function test_component_similarity_and_unreadability(?float $a, ?float $b, float $expected): void
    {
        $calculator = new PhaseProfileComparisonCalculator;
        $current = new PhaseProfile(array_fill(0, 7, $a));
        $reference = new PhaseProfile(array_fill(0, 7, $b));
        $this->assertSame(array_fill(0, 7, $expected), $calculator->contributions($current, $reference));
        $this->assertSame($expected, $calculator->calculate($current, $reference));
    }

    public function test_bootstrap_sawtooth_uses_all_seven_slots(): void
    {
        $calculator = new PhaseProfileComparisonCalculator;
        $values = array_fill(0, 7, null);
        $this->assertSame(0.0, $calculator->calculate(new PhaseProfile($values), PhaseProfile::origin()));
        foreach ([1, 1, 1, 0, 1, 1, 1] as $slot => $interval) {
            $values[$slot] = $interval;
            $this->assertEqualsWithDelta([-1, -2, -3, -2, -3, -4, -3][$slot] / 7,
                $calculator->calculate(new PhaseProfile($values), PhaseProfile::origin()), 1e-12);
            if ($slot === 0) {
                $this->assertSame([-1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0],
                    $calculator->contributions(new PhaseProfile($values), PhaseProfile::origin()));
            }
        }
    }

    public function test_independently_developed_settlement_profiles_remain_consonant(): void
    {
        $offsets = new PhaseProfile([2, 2 / 5, 31 / 13, 0, 47 / 23, 35 / 9, 7 / 4]);
        $primes = new PhaseProfile([5 / 4, 1 / 3, 44 / 13, 0, 22 / 15, 34 / 11, 7 / 4]);
        $calculator = new PhaseProfileComparisonCalculator;
        $this->assertSame(1.0, $calculator->calculate($offsets, $offsets));
        $this->assertSame(1.0, $calculator->calculate($primes, $primes));
        $this->assertEqualsWithDelta(.621547, $calculator->calculate($primes, $offsets), 1e-6);
        $this->assertSame($calculator->calculate($primes, $offsets), $calculator->calculate($offsets, $primes));
        foreach ([$primes, $offsets] as $profile) {
            $this->assertEqualsWithDelta(-.551020, $calculator->calculate($profile, PhaseProfile::origin()), 1e-6);
        }
    }

    public static function invalidProfiles(): array
    {
        return [[[1]], [[-1, 0, 0, 0, 0, 0, 0]], [[INF, 0, 0, 0, 0, 0, 0]],
            [[NAN, 0, 0, 0, 0, 0, 0]], [['1', 0, 0, 0, 0, 0, 0]], [[1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0]]];
    }

    #[DataProvider('invalidProfiles')]
    public function test_profile_rejects_noncanonical_or_invalid_components(array $values): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PhaseProfile($values);
    }
}
