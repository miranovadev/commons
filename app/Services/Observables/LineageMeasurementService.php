<?php

namespace App\Services\Observables;

use App\Domain\Observables\LineageComparison;
use App\Domain\Observables\LocalMeasurements;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class LineageMeasurementService
{
    public const string VERSION = 'evidence-commitment-v8';

    public function __construct(
        private LocalMeasurementService $local,
        private ContextLineageComparisonService $lineages,
    ) {}

    public static function attenuation(int $distance): float
    {
        return 1 / (1 + $distance) ** 2;
    }

    public function prospectiveCommit(Proposition $gate, Phase $phase, ?ParticipationContext $context = null): LocalMeasurements
    {
        $comparison = $this->lineages->prospectiveCommit($gate, $phase, $context);
        // The synthetic candidate is appended by the same comparison builder used locally.
        $candidate = $comparison->current->propositions->last();

        return $this->calculate($candidate, $comparison, $context);
    }

    public function calculate(Proposition $position, ?LineageComparison $headComparison = null, ?ParticipationContext $context = null): LocalMeasurements
    {
        $totals = ['correspondence' => 0.0, 'chirality' => 0.0, 'depth' => 0.0, 'cadence' => 0.0, 'closureValue' => 0.0];
        $mass = 0.0;
        foreach ($this->positions($position, $context) as [$proposition, $distance]) {
            $comparison = $distance === 0 ? $headComparison : null;
            $calculate = fn () => $this->local->calculate($proposition, $comparison ?? ($context !== null ? $this->lineages->for($proposition, $context) : null));
            $local = $context !== null && $proposition->exists && $comparison === null
                ? $context->remember('local:'.$proposition->id, $calculate) : $calculate();
            $weight = self::attenuation($distance);
            // Every traversed position contributes denominator mass, including neutral values.
            $mass += $weight;
            foreach ($totals as $name => $total) {
                $value = $name === 'closureValue' ? $local->closure()->average() : $local->{$name};
                $totals[$name] += $value * $weight;
            }
        }

        return new LocalMeasurements(...array_map(fn (float $sum): float => round($sum / $mass, 7), $totals));
    }

    /**
     * Reverse BFS: unique positions at minimum edge distance, including each provenance
     * Committal but never walking beyond it. A prospective/actual Committal head still
     * measures its incoming lineage. No participant read limit or global Horizon scan.
     *
     * @return Collection<int, array{Proposition, int}>
     */
    public function positions(Proposition $position, ?ParticipationContext $context = null): Collection
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $frontier = collect([$position]);
        $seen = [];
        $positions = collect();
        $distance = 0;
        while ($frontier->isNotEmpty()) {
            $expand = collect();
            foreach ($frontier as $proposition) {
                if (isset($seen[$proposition->id])) {
                    continue;
                }
                $seen[$proposition->id] = true;
                $positions->push([$proposition, $distance]);
                if ($distance === 0 || $proposition->tier !== 'committal') {
                    $expand->push($proposition->id);
                }
            }
            $parentIds = $context !== null ? $expand->flatMap(fn ($id) => $context->parents->get($id, collect())->pluck('source_proposition_id')) : DB::table('supersessions')->whereIn('target_proposition_id', $expand)
                ->pluck('source_proposition_id');
            if ($distance === 0 && ! $position->exists) {
                $parentIds = $parentIds->merge($position->parents->pluck('id'));
            }
            $frontier = $context !== null ? $context->nodes($parentIds)->whereNotIn('id', array_keys($seen))->sortBy('id')->values() : Proposition::query()->whereIn('id', $parentIds)->whereNotIn('id', array_keys($seen))
                ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $substrateId))
                ->with('phase.cycle.participant')->orderBy('id')->get();
            $distance++;
        }

        return $positions;
    }
}
