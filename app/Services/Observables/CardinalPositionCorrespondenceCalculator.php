<?php

namespace App\Services\Observables;

use App\Domain\Observables\CardinalMorphology;

final class CardinalPositionCorrespondenceCalculator
{
    public function calculate(CardinalMorphology $current, CardinalMorphology $reference): float
    {
        $limit = $current->primaryDepth;
        $currentRows = $this->rows($current, $limit);
        $referenceRows = $this->rows($reference, $limit);
        $depths = collect([...array_keys($currentRows), ...array_keys($referenceRows)])
            ->unique()->sort()->values();

        if ($depths->isEmpty()) {
            return 0.0;
        }

        return round($depths->avg(fn (int $depth) => self::rowScore(
            $currentRows[$depth] ?? [],
            $referenceRows[$depth] ?? [],
        )), 7);
    }

    /** @return array<int, list<int>> */
    private function rows(CardinalMorphology $morphology, int $limit): array
    {
        $rows = [];

        foreach ($morphology->positions as $id => $position) {
            if ($position->depth > $limit || ! $morphology->propositions->has($id)) {
                continue;
            }

            $rows[$position->depth][] = $position->lateral;
        }

        foreach ($rows as &$positions) {
            sort($positions, SORT_NUMERIC);
        }
        unset($positions);

        return $rows;
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     */
    public static function rowScore(array $left, array $right): float
    {
        sort($left, SORT_NUMERIC);
        sort($right, SORT_NUMERIC);

        $count = max(count($left), count($right));
        if ($count === 0) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $sum += isset($left[$i], $right[$i])
                ? self::positionSimilarity($left[$i], $right[$i])
                : -1.0;
        }

        return round($sum / $count, 7);
    }

    public static function positionSimilarity(int $a, int $b): float
    {
        if ($a < 1 || $b < 1) {
            throw new \InvalidArgumentException('Lateral positions are one-based.');
        }

        return max(-1, min(1, 1 - (2 * abs($a - $b) / ($a + $b))));
    }
}
