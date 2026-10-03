<?php

namespace Diagnostics\Formation;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\LineageComparison;
use App\Domain\Observables\LocalMeasurements;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Observables\ClosureCalculator;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LocalMeasurementService;

class SemanticBoundary extends \RuntimeException {}

final class EventOrder
{
    public int $clock = 0;

    public array $cycles = [];

    public array $acts = [];
}

/** Container replacement confined to the opt-in diagnostic process. */
class CandidateLocalMeasurements extends LocalMeasurementService
{
    public function chiralityComponents(Proposition $proposition, ?LineageComparison $comparison = null): ChiralityValues
    {
        $comparison ??= app(ContextLineageComparisonService::class)->for($proposition);
        $observed = $this->diagnose($comparison);

        return new ChiralityValues($observed['orientation'], $observed['attestation_cadence']);
    }

    public function calculate(Proposition $proposition, ?LineageComparison $comparison = null, bool $includeClosure = true): LocalMeasurements
    {
        $comparison ??= app(ContextLineageComparisonService::class)->for($proposition);
        $canonical = parent::calculate($proposition, $comparison, $includeClosure);
        $chirality = $this->chiralityComponents($proposition, $comparison);

        return new LocalMeasurements($canonical->correspondence, $chirality->average(),
            $canonical->depth, $canonical->cadence, $canonical->closureValue, $chirality,
            $canonical->correspondenceComponents, $canonical->closure());
    }

    public function diagnose(LineageComparison $comparison): array
    {
        $calc = app(ChiralityCalculator::class);
        $lean = fn ($l) => $calc->leanFor($calc->activeIds($l->propositions, $l->supersessions), $l->context);
        $current = $lean($comparison->current);
        $previous = $comparison->previous === null ? null : $lean($comparison->previous);
        $a = $this->intervals($comparison->current);
        $b = $comparison->previous === null ? ['intervals' => [], 'cv' => null, 'status' => 'no_previous_domain', 'acts' => 0] : $this->intervals($comparison->previous);
        $rootReference = $comparison->previous !== null
            && (int) $comparison->previous->horizon->sequence === 1;

        // Origin calibrates polarity but is not an ordinary empirical lean sample.
        // Its constitutional cadence is -1. The first developed Domain measures
        // formation of its own rhythm; later Domains use ordinary comparative CV.
        if ($comparison->previous === null) {
            $orientation = 0.0;
            $cadence = -1.0;
            $cadenceStatus = 'constitutional_origin';
        } elseif ($rootReference) {
            $previous = 0.0;
            $orientation = round(($current - $previous) / 2, 7);
            if (count($a['intervals']) < 2) {
                $cadence = 0.0;
                $cadenceStatus = 'formation_immature';
            } elseif ($a['cv'] === null) {
                throw new SemanticBoundary('First-rhythm all-zero interval CV becomes necessary: '.json_encode($a));
            } else {
                $cadence = round(max(-1, min(1, (1 - $a['cv']) / (1 + $a['cv']))), 7);
                $cadenceStatus = 'formation_cv';
            }
        } else {
            $orientation = round(($current - $previous) / 2, 7);
            if (count($a['intervals']) < 2 || count($b['intervals']) < 2) {
                $cadence = 0.0;
                $cadenceStatus = 'comparative_immature';
            } elseif ($a['cv'] === null || $b['cv'] === null) {
                throw new SemanticBoundary('Comparative all-zero interval CV becomes necessary: '.json_encode([$a, $b]));
            } else {
                $cadence = ClosureCalculator::variabilitySimilarity($a['cv'], $b['cv']);
                $cadenceStatus = 'comparative_cv';
            }
        }

        return ['current_lean' => $current, 'previous_lean' => $previous,
            'orientation' => $orientation, 'orientation_mature' => $comparison->previous !== null,
            'current_history' => $a, 'previous_history' => $b,
            'attestation_cadence' => $cadence, 'attestation_cadence_mature' => ! in_array($cadenceStatus, ['formation_immature', 'comparative_immature'], true),
            'cadence_status' => $cadenceStatus, 'cadence_contribution' => $cadence,
            'component_gate' => str_ends_with($cadenceStatus, '_immature') ? 'immature_direction_hysteresis' : 'direction_agreement'];
    }

    private function intervals($lineage): array
    {
        $order = app(EventOrder::class);
        $entries = $lineage->propositions->map(fn ($p) => $p->phase->cycle->entry_committal_id)
            ->merge($lineage->propositions->where('tier', 'committal')->pluck('id'))->filter()->unique();
        $cycles = Cycle::whereIn('entry_committal_id', $entries)->where('context_horizon_id', $lineage->horizon->id)->get();
        $origins = $lineage->propositions->filter(fn ($p) => $p->tier === 'committal' && $p->phase->cycle->entry_committal_id === null)
            ->map(fn ($p) => $p->phase->cycle);
        $cycles = $cycles->merge($origins)->unique('id');
        $acts = Attestation::whereIn('proposition_id', $lineage->propositions->pluck('id'))->get()
            ->sortBy(fn ($a) => $order->acts[$a->id])->values();
        $intervals = [];
        for ($i = 1; $i < $acts->count(); $i++) {
            $from = $order->acts[$acts[$i - 1]->id];
            $to = $order->acts[$acts[$i]->id];
            $intervals[] = $cycles->filter(fn ($c) => $order->cycles[$c->id] > $from && $order->cycles[$c->id] < $to)->count();
        }
        $mean = count($intervals) ? array_sum($intervals) / count($intervals) : null;
        $cv = $mean === null || $mean == 0 ? null : sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $intervals)) / count($intervals)) / $mean;

        return ['acts' => $acts->count(), 'intervals' => $intervals, 'mean' => $mean, 'cv' => $cv,
            'status' => $mean === null ? 'no_intervals' : ($mean == 0 ? 'undefined_zero_mean' : (count($intervals) === 1 ? 'singleton_unresolved' : 'empirical_cv'))];
    }
}
