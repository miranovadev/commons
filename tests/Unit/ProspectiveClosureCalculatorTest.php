<?php

namespace Tests\Unit;

use App\Services\Observables\PhaseProfileComparisonCalculator;
use App\Services\Observables\ProspectiveClosureCalculator;
use PHPUnit\Framework\TestCase;

class ProspectiveClosureCalculatorTest extends TestCase
{
    private function calculator(): ProspectiveClosureCalculator
    {
        return new ProspectiveClosureCalculator(new PhaseProfileComparisonCalculator);
    }

    private function observations(array $slots): array
    {
        $rows = [];
        foreach ($slots as $phase => $values) {
            foreach ($values as $value) {
                $rows[] = ['phase' => $phase + 1, 'interval' => $value];
            }
        }

        return $rows;
    }

    public function test_origin_constants_do_not_require_an_empirical_population(): void
    {
        $origin = $this->calculator()->origin();
        foreach (['depth', 'cadence', 'closure', 'historical_extent', 'historical_rhythm'] as $name) {
            $this->assertSame(-1.0, $origin[$name]);
        }
        $this->assertNull($origin['historical_orientation']);
        $this->assertNull($origin['historical_phase_profile']);
    }

    public function test_repeated_domains_use_raw_equally_weighted_intervals_including_origin(): void
    {
        // E0 + selected E1 + developing E3, and selected S1 + S2.
        $e = $this->observations([[0], [0, 2, 4], [0, 0, 0], [0, 2, 4], [0, 4, 2], [0, 0, 0], [1, 2, 3]]);
        $s = $this->observations([[], [2, 4, 6], [0, 0], [1, 3], [3, 1], [0, 0], [1, 3]]);
        $result = $this->calculator()->calculate('emergence', ['emergence' => 3, 'settlement' => 2],
            ['emergence' => [1, 7], 'settlement' => [10, 12]],
            ['emergence' => $e, 'settlement' => $s], ['emergence' => [.5, 0, -.5], 'settlement' => []]);
        $this->assertSame([1, 3, 3, 3, 3, 3, 3], $result['domain_profiles']['emergence']['sample_counts']);
        $this->assertSame([0.0, 2.0, 0.0, 2.0, 2.0, 0.0, 2.0], $result['domain_profiles']['emergence']['means']);
        $this->assertSame(4.0, $result['domain_profiles']['settlement']['means'][1]);
        $this->assertSame([0.0, 0.0, 1.0, 1.0, 1.0, 1.0, 1.0], $result['phase_contributions']);
        $this->assertSame([false, true, true, true, true, true, true], $result['availability']['historical_phase_profile']);
        $this->assertSame(round(5 / 7, 7), $result['historical_phase_profile']);
        $this->assertEqualsWithDelta(11 / 21, $result['historical_extent'], 1e-7);
        $this->assertSame(-.7575758, $result['cadence']);
        $this->assertSame(0.0, $result['historical_orientation']);
        $this->assertSame(3, $result['orientation_sample_count']);
        $this->assertTrue($result['availability']['historical_orientation']);
    }

    public function test_zero_and_unavailable_remain_distinct_and_zero_retains_independent_directions(): void
    {
        $result = $this->calculator()->calculate('settlement', ['emergence' => 1, 'settlement' => 1],
            ['emergence' => [1], 'settlement' => [1]],
            ['emergence' => [], 'settlement' => [['phase' => 1, 'interval' => null]]],
            ['emergence' => [], 'settlement' => [0.0]],
            ['historical_extent_direction' => 'positive', 'historical_rhythm_direction' => 'negative']);
        $this->assertSame(0.0, $result['closure']);
        $this->assertFalse($result['availability']['cadence']);
        $this->assertTrue($result['availability']['historical_orientation']);
        $this->assertSame('positive', $result['historical_extent_direction']);
        $this->assertSame('negative', $result['historical_rhythm_direction']);
        $this->assertFalse($result['admissible']);
        $profile = $this->calculator()->profile([['phase' => 2, 'interval' => 0], ['phase' => 2, 'interval' => null]]);
        $this->assertSame(1, $profile['sample_counts'][1]);
        $this->assertSame(0.0, $profile['means'][1]);
    }
}
