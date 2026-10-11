<?php

namespace App\Services\Observables;

use App\Domain\Observables\PhaseProfile;
use App\Enums\ObservableDirection;
use InvalidArgumentException;

/** Arithmetic only: callers select and freeze the authoritative observation population. */
final readonly class ProspectiveClosureCalculator
{
    public function __construct(private PhaseProfileComparisonCalculator $profiles) {}

    /** Each available interval counts once; provenance never changes its weight. */
    public function profile(array $observations): array
    {
        $sums = $counts = array_fill(0, 7, 0);
        foreach ($observations as $observation) {
            $phase = $observation['phase'];
            $interval = $observation['interval'];
            if (! is_int($phase) || $phase < 1 || $phase > 7
                || ($interval !== null && (! is_numeric($interval) || ! is_finite((float) $interval) || $interval < 0))) {
                throw new InvalidArgumentException('Historical Phase observations require a canonical Phase and nonnegative interval or null.');
            }
            if ($interval !== null) {
                $sums[$phase - 1] += $interval;
                $counts[$phase - 1]++;
            }
        }

        return ['means' => array_map(fn ($sum, $count) => $count === 0 ? null : (float) $sum / $count, $sums, $counts),
            'sample_counts' => $counts];
    }

    /** Constitutional bootstrap is separate from ordinary empirical arithmetic. */
    public function origin(): array
    {
        return ['depth' => -1.0, 'cadence' => -1.0, 'historical_phase_profile' => null,
            'historical_orientation' => null, 'historical_extent' => -1.0, 'historical_rhythm' => -1.0,
            'closure' => -1.0, 'historical_extent_direction' => 'negative',
            'historical_rhythm_direction' => 'negative', 'depth_direction' => 'negative', 'cadence_direction' => 'negative', 'admissible' => true,
            'availability' => ['depth' => 'constitutional', 'cadence' => 'constitutional',
                'historical_phase_profile' => 'not_applicable', 'historical_orientation' => 'not_applicable']];
    }

    /**
     * Domain histories contain raw intervals, not Horizon averages. Orientation is
     * already resolved locally; zero participates in its sum and denominator.
     */
    public function calculate(string $domain, array $depths, array $intervals, array $phaseObservations,
        array $orientations, array $previous = []): array
    {
        if (! in_array($domain, ['emergence', 'settlement'], true)) {
            throw new InvalidArgumentException('Closure requires a creating Domain.');
        }
        $other = $domain === 'emergence' ? 'settlement' : 'emergence';
        $profiles = array_map($this->profile(...), $phaseObservations);
        $a = new PhaseProfile($profiles[$domain]['means']);
        $b = new PhaseProfile($profiles[$other]['means']);
        $contributions = $this->profiles->contributions($a, $b);
        $available = array_map(fn ($left, $right) => $left !== null && $right !== null, $a->values, $b->values);
        $phaseProfile = round(array_sum($contributions) / 7, 7);
        $depthAvailable = array_sum($depths) > 2 && min($depths) > 0;
        $depth = $depthAvailable ? ClosureCalculator::similarity($depths[$domain], $depths[$other]) : 0.0;
        $cvs = array_map(fn ($values) => count($values) < 2 ? null : ClosureCalculator::coefficientOfVariation($values), $intervals);
        $cadenceAvailable = $cvs[$domain] !== null && $cvs[$other] !== null;
        $cadence = $cadenceAvailable ? ClosureCalculator::variabilitySimilarity($cvs[$domain], $cvs[$other]) : 0.0;
        $values = array_values(array_filter($orientations[$domain], fn ($value) => $value !== null));
        foreach ($values as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || abs($value) > 1) {
                throw new InvalidArgumentException('Historical Orientation requires resolved local values in [-1,1].');
            }
        }
        $orientation = $values === [] ? 0.0 : round(array_sum($values) / count($values), 7);
        $extent = round(($depth + $phaseProfile) / 2, 7);
        $rhythm = round(($cadence + $orientation) / 2, 7);
        $extentDirection = ObservableDirection::after($extent, ObservableDirection::from($previous['historical_extent_direction'] ?? 'negative'));
        $rhythmDirection = ObservableDirection::after($rhythm, ObservableDirection::from($previous['historical_rhythm_direction'] ?? 'negative'));

        return ['depth' => $depth, 'cadence' => $cadence, 'historical_phase_profile' => $phaseProfile,
            'historical_orientation' => $orientation, 'historical_extent' => $extent, 'historical_rhythm' => $rhythm,
            'closure' => round(($extent + $rhythm) / 2, 7),
            'historical_extent_direction' => $extentDirection->value, 'historical_rhythm_direction' => $rhythmDirection->value,
            'depth_direction' => ObservableDirection::after($depth, ObservableDirection::from($previous['depth_direction'] ?? 'negative'))->value,
            'cadence_direction' => ObservableDirection::after($cadence, ObservableDirection::from($previous['cadence_direction'] ?? 'negative'))->value,
            'admissible' => $extentDirection === $rhythmDirection,
            'availability' => ['depth' => $depthAvailable, 'cadence' => $cadenceAvailable,
                'historical_phase_profile' => $available, 'historical_orientation' => $values !== []],
            'domain_profiles' => $profiles, 'phase_contributions' => $contributions,
            'orientation_sample_count' => count($values), 'domain_cvs' => $cvs];
    }
}
