<?php

namespace App\Services\Observables;

use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\ClosureValues;
use App\Domain\Observables\LineageComparison;
use App\Models\Proposition;
use DomainException;

final readonly class ClosureCalculator
{
    public function __construct(
        private ReinforcementService $reinforcement,
    ) {}

    public function calculate(LineageComparison $comparison): float
    {
        return $this->components($comparison)->average();
    }

    public function components(LineageComparison $comparison): ClosureValues
    {
        if ($comparison->isBootstrap()) {
            return new ClosureValues(-1, -1);
        }

        $depth = $this->similarity(
            $this->depth($comparison->current),
            $this->depth($comparison->previous),
        );
        $cadence = $this->similarity(
            $this->coefficientOfVariation($comparison->current),
            $this->coefficientOfVariation($comparison->previous),
        );

        return new ClosureValues(round($depth, 7), round($cadence, 7));
    }

    private function depth(DomainLineage $lineage): float
    {
        $weights = $this->reinforcement->weights(
            $lineage->propositions,
            $lineage->horizon->substrate,
        );
        $children = $lineage->supersessions
            ->groupBy('source_proposition_id');
        $memo = [];
        $visiting = [];

        $visit = function (string $id) use (
            &$visit,
            &$memo,
            &$visiting,
            $children,
            $weights,
        ): float {
            if (isset($memo[$id])) {
                return $memo[$id];
            }

            if (isset($visiting[$id])) {
                throw new DomainException(
                    'A cyclic Supersession graph cannot be measured.'
                );
            }

            $visiting[$id] = true;
            $longestChild = 0.0;

            foreach ($children->get($id, collect()) as $edge) {
                $longestChild = max(
                    $longestChild,
                    $visit($edge->target_proposition_id),
                );
            }

            unset($visiting[$id]);

            return $memo[$id] = $weights->get($id, 1)
                + $longestChild;
        };

        return (float) $lineage->propositions
            ->map(
                fn (Proposition $proposition) => $visit(
                    $proposition->getKey()
                ),
            )
            ->max();
    }

    private function coefficientOfVariation(
        DomainLineage $lineage,
    ): float {
        $intervals = $lineage->propositions
            ->groupBy(
                fn (Proposition $proposition) => (string) $proposition
                    ->phase
                    ->cycle
                    ->participant_id,
            )
            ->flatMap(function ($propositions) {
                $sequences = $propositions
                    ->sortBy(
                        fn (Proposition $proposition) => $proposition
                            ->ledgerEntry
                            ->sequence,
                    )
                    ->pluck('phase.cycle.sequence')
                    ->values();

                return $sequences
                    ->slice(1)
                    ->values()
                    ->map(
                        fn ($sequence, int $index) => max(
                            1,
                            (int) $sequence - (int) $sequences[$index],
                        ),
                    );
            })
            ->values();

        if ($intervals->isEmpty()) {
            return 0;
        }

        $mean = (float) $intervals->average();

        if ($mean === 0.0) {
            return 0;
        }

        $variance = (float) $intervals
            ->map(fn ($interval) => ($interval - $mean) ** 2)
            ->average();

        return sqrt($variance) / $mean;
    }

    private function similarity(float $left, float $right): float
    {
        $maximum = max($left, $right);

        if ($maximum === 0.0) {
            return 1;
        }

        return max(
            -1,
            min(1, 1 - 2 * (abs($left - $right) / $maximum)),
        );
    }
}
