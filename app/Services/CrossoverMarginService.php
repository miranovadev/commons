<?php

namespace App\Services;

use App\Domain\Observables\ClosureValues;
use App\Domain\Observables\ObservableValues;
use App\Enums\ContextDomain;
use App\Enums\ObservableDirection;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ClosureCalculator;

/** Read-only, current committed geometry. Neither exhaustion nor future capacity. */
final readonly class CrossoverMarginService
{
    public function inspect(Cycle $cycle): array
    {
        $seed = Proposition::find($cycle->entry_committal_id);
        $snapshot = $seed?->commitmentSnapshot;
        $domain = $cycle->contextHorizon->domain;
        $base = ['governing_committal_id' => $seed?->id, 'domain' => $domain->value,
            'correspondence' => $snapshot?->correspondence, 'chirality' => $snapshot?->chirality,
            'prospective_depth' => null, 'admissible_cadence_bound' => null,
            'tension_bound' => null, 'crossover_margin' => null,
            'closed' => false];
        if ($snapshot === null) {
            return [...$base, 'unavailable_reason' => 'committed_geometry_unavailable'];
        }
        // A hypothetical next Commit belongs to this entry Region and Domain.
        // No candidate, Phase, measurement or lineage edge is persisted.
        $phase = new Phase;
        $phase->setRelation('cycle', $cycle);
        $candidate = new Proposition;
        $candidate->forceFill(['tier' => 'committal']);
        $candidate->setRelation('phase', $phase);
        $candidate->setRelation('commitSources', collect([$seed]));
        try {
            $depth = app(ClosureCalculator::class)->components($candidate)->depth;
        } catch (ClosureHistoryUnavailable) {
            // Missing legacy history is not proof of a closed crossover.
            return [...$base, 'unavailable_reason' => 'closure_history_unavailable'];
        }
        $direction = ObservableDirection::after($depth, $snapshot->depth_direction ?? ObservableDirection::Negative);
        // Zero is the optimistic endpoint (possibly only a supremum under
        // hysteresis). A non-crossing bound still proves closure conservatively.
        $cadence = $domain === ContextDomain::Settlement
            ? ($direction === ObservableDirection::Positive ? 1.0 : 0.0)
            : ($direction === ObservableDirection::Negative ? -1.0 : 0.0);
        $closure = (new ClosureValues($depth, $cadence))->average();
        $tension = app(ProjectionService::class)->field(new ObservableValues(
            $snapshot->correspondence, $snapshot->chirality, $closure))['tension'];
        $margin = $domain === ContextDomain::Settlement ? $tension : -$tension;

        return [...$base, 'prospective_depth' => $depth, 'admissible_cadence_bound' => $cadence,
            'tension_bound' => $tension,
            'crossover_margin' => $margin, 'closed' => $margin <= 0];
    }
}
