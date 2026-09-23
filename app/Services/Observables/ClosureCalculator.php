<?php

namespace App\Services\Observables;

use App\Domain\Observables\ClosureValues;
use App\Domain\Observables\LineageComparison;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;

final readonly class ClosureCalculator
{
    public function __construct(
        private ReinforcementService $reinforcement,
        private CommittalLineageService $lineage,
    ) {}

    public function localComponents(Proposition $proposition, LineageComparison $comparison): ClosureValues
    {
        $context = $comparison->current->context;
        $records = $this->lineage->records($proposition, $context);
        $snapshot = $records->get($proposition->id);
        if ($snapshot !== null) {
            // Persisted measurements remain historical, including pre-upgrade snapshots.
            return new ClosureValues($snapshot->depth ?? 0, $snapshot->cadence ?? 0, $snapshot->closure);
        }
        $structural = $this->components($proposition, $context);
        if ($proposition->phase->cycle->entry_committal_id === null && $proposition->tier === 'committal') {
            return $structural;
        }
        $mass = $this->reinforcement->measurementCount($proposition, $context)
            * $this->reinforcement->receiptIncrement($comparison->current->horizon->substrate);

        return new ClosureValues($structural->depth, $structural->cadence,
            round(($structural->depth + $structural->cadence + $mass) / (2 + $mass), 7));
    }

    public function components(Proposition $position, ?ParticipationContext $context = null): ClosureValues
    {
        $cycle = $position->phase->cycle;
        $ancestors = $this->lineage->ancestors($position, $context);
        if ($position->tier !== 'committal') {
            // Expressive work inherits its governing committed structure. It does
            // not increment Domain-run depth or create a hypothetical interval.
            $governor = $ancestors->first();
            return new ClosureValues($governor?->depth ?? -1, $governor?->cadence ?? -1);
        }
        if ($ancestors->isEmpty()) {
            if ($cycle->contextHorizon->parent_context_horizon_id !== null) {
                throw new DomainException('Only Origin may have no entry Committal.');
            }
            return new ClosureValues(-1, -1);
        }
        if ($ancestors->count() === 1) {
            return new ClosureValues(0, 0);
        }
        $runs = [[$cycle->contextHorizon->domain->value, 1]];
        foreach ($ancestors as $ancestor) {
            $last = count($runs) - 1;
            if ($runs[$last][0] === $ancestor->creating_domain) {
                $runs[$last][1]++;
            } else {
                $runs[] = [$ancestor->creating_domain, 1];
            }
        }
        if (count($runs) < 2) {
            throw new ClosureHistoryUnavailable('Measured Depth requires a preceding opposite-Domain committed run.');
        }
        $previousInterval = $ancestors->first()->cadence_interval;
        if ($previousInterval === null) {
            throw new ClosureHistoryUnavailable('The preceding frozen Cadence interval is unavailable.');
        }

        return new ClosureValues(self::similarity($runs[0][1], $runs[1][1]),
            self::similarity($this->lineage->interval($position, $context), $previousInterval));
    }

    public static function similarity(int $left, int $right): float
    {
        if ($left < 1 || $right < 1) {
            throw new DomainException('Measured committed depths and intervals must be positive.');
        }
        return round(max(-1, min(1, 1 - 2 * abs($left - $right) / max($left, $right))), 7);
    }
}
