<?php

namespace App\Services\Observables;

use App\Domain\Observables\ClosureValues;
use App\Domain\Observables\DomainLineage;
use App\Domain\Observables\LineageComparison;
use App\Models\Proposition;
use App\Services\PropositionStateService;
use DomainException;
use Illuminate\Support\Collection;

final readonly class ClosureCalculator
{
    public function __construct(
        private ReinforcementService $reinforcement,
        private ChiralityCalculator $chirality,
        private PropositionStateService $state,
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

    /** Mean structural extension of active heads; history supplies paths, not observations. */
    public function depth(DomainLineage $lineage): float
    {
        $heads = $this->activeHeads($lineage);
        $weights = $this->reinforcement->weights(
            $heads,
            $lineage->horizon->substrate,
            $lineage->context,
        );
        $parents = $lineage->supersessions->groupBy('target_proposition_id');
        $memo = [];
        $visiting = [];

        $visit = function (string $id) use (
            &$visit,
            &$memo,
            &$visiting,
            $parents,
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
            $longestParent = 0.0;

            foreach ($parents->get($id, collect()) as $edge) {
                $longestParent = max(
                    $longestParent,
                    $visit($edge->source_proposition_id),
                );
            }

            unset($visiting[$id]);

            return $memo[$id] = 1 + $longestParent;
        };

        return (float) $heads
            ->map(
                fn (Proposition $proposition) => $visit(
                    $proposition->getKey()
                ) * $weights->get($proposition->getKey(), 1),
            )
            ->average();
    }

    /** Population CV of persisted Phase touches; no cycle sequence or clock inputs. */
    public function coefficientOfVariation(
        DomainLineage $lineage,
    ): float {
        $touches = $this->activeHeads($lineage)
            ->map(fn (Proposition $proposition) => $this->state->touchCount($proposition, $lineage->context));

        if ($touches->isEmpty()) {
            return 0;
        }

        $mean = (float) $touches->average();

        if ($mean === 0.0) {
            return 0;
        }

        $variance = (float) $touches
            ->map(fn ($touchCount) => ($touchCount - $mean) ** 2)
            ->average();

        return sqrt($variance) / $mean;
    }

    private function activeHeads(DomainLineage $lineage): Collection
    {
        return $lineage->propositions->whereIn('id', $this->chirality->activeIds(
            $lineage->propositions, $lineage->supersessions,
        ));
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
