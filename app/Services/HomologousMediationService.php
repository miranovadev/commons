<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Dominant homologous Mediate comparison pool; final action policy stays with reconciliation. */
final readonly class HomologousMediationService
{
    public function __construct(
        private HomeostaticPopulationService $populations,
        private HomeostaticDirectionService $directions,
        private MediationAnchorService $mediation,
        private PhasePositionService $positions,
        private CommitRegionService $regions,
        private PropositionMeasurementService $measurements,
        private PropositionAvailabilityService $availability,
    ) {}

    /**
     * The causal Read identifies the coordinate; the full population sets direction.
     * Member qualification uses each Read's own Correspondence, never its Gate child.
     * Context hydration includes structural operands and their measurement ancestry.
     * Receipt availability remains independently qualified by the acting Region.
     * Final action policy (including exhausted continuations and directed pairs) remains
     * the responsibility of ReconciliationOptionService.
     *
     * @return array{reads: Collection, gates: Collection}
     */
    public function poolFor(Phase $phase, ?ParticipationContext $context = null): array
    {
        if ($phase->kind !== PhaseKind::Mediate) {
            return ['reads' => collect(), 'gates' => collect()];
        }
        $context ??= new ParticipationContext($phase);

        return $context->remember('homologous-mediate-pool:'.$phase->id, fn () => $this->resolve($phase, $context));
    }

    /** Homologous discovery also includes local members; only foreign Regions hide evidence. */
    public function foreignGateIdsFor(Phase $phase, ParticipationContext $context): Collection
    {
        $seeds = $context->seedIds();

        return $this->poolFor($phase, $context)['gates']
            ->filter(fn ($gate) => ($seeds[$gate->id] ?? null) !== $phase->cycle->entry_committal_id)
            ->pluck('id');
    }

    /** Pre-hydration discovery never constructs a ParticipationContext. */
    public function structuralPoolFor(Phase $phase, ?ParticipationContext $context = null): array
    {
        return $context === null ? $this->discover($phase)
            : $context->remember('homologous-mediate-structure:'.$phase->id, fn () => $this->discover($phase));
    }

    /** Batch look-ahead for one actor's Region/Horizon without changing membership. */
    public function structuralPoolsFor(Collection $phases, ParticipationContext $context): array
    {
        $anchors = $phases->mapWithKeys(function ($phase) use ($context): array {
            $anchor = $this->mediation->anchorFor($phase);

            return [$phase->id => $anchor !== null && $this->positions->matches($anchor, $phase, PhaseKind::Constrain)
                && $this->regions->seedFor($anchor, $context)?->id === $phase->cycle->entry_committal_id ? $anchor : null];
        });
        $result = [];
        // All supplied probes share an actor's entry and Horizon. The canonical
        // Constrain population is identical; only the excluded anchor differs.
        $anchor = $anchors->filter()->first();
        $population = $anchor === null ? collect() : $this->populations->forPosition($anchor);
        $gates = $anchor === null ? collect() : $this->discoverGates($phases->first(), $population);
        $edges = Supersession::whereIn('source_proposition_id', $population->pluck('id'))
            ->whereIn('target_proposition_id', $gates->pluck('id'))->get()->groupBy('target_proposition_id');
        foreach ($phases as $phase) {
            $anchor = $anchors[$phase->id];
            $reads = $anchor === null ? collect() : $population->where('id', '!=', $anchor->id)->values();
            $ids = $reads->pluck('id')->flip();
            $result['homologous-mediate-structure:'.$phase->id] = ['anchor' => $anchor,
                'population' => $anchor === null ? collect() : $population, 'reads' => $reads,
                'gates' => $gates->filter(fn ($gate) => $edges->get($gate->id, collect())
                    ->contains(fn ($edge) => $ids->has($edge->source_proposition_id)))->values()];
        }

        return $result;
    }

    private function discover(Phase $phase): array
    {
        $empty = ['reads' => collect(), 'gates' => collect(), 'population' => collect(), 'anchor' => null];
        $anchor = $this->mediation->anchorFor($phase);
        if ($anchor === null || ! $this->positions->matches($anchor, $phase, PhaseKind::Constrain)) {
            return $empty;
        }
        $owner = $this->regions->seedFor($anchor);
        if ($owner === null || $owner->id !== $phase->cycle->entry_committal_id) {
            return $empty;
        }
        $population = $this->populations->forPosition($anchor);
        $reads = $population->where('id', '!=', $anchor->id)->values();

        $gates = $this->discoverGates($phase, $reads);

        return ['reads' => $reads, 'gates' => $gates, 'population' => $population, 'anchor' => $anchor];
    }

    private function discoverGates(Phase $phase, Collection $reads): Collection
    {
        $domain = $phase->cycle->contextHorizon->domain;
        $register = $domain->registerFor(PhaseKind::Disperse);

        return Proposition::query()->active()->where('tier', 'expressive')->where('register', $register)
            ->whereHas('phase', fn ($q) => $q->where('kind', PhaseKind::Disperse->value)->where('write_register', $register)
                ->whereHas('cycle', fn ($q) => $q
                    ->whereHas('contextHorizon', fn ($q) => $q->where('domain', $domain->value))
                    ->whereHas('participant', fn ($q) => $q->where('substrate_id', $phase->cycle->participant->substrate_id))))
            ->whereHas('parents', fn ($q) => $q->whereKey($reads->pluck('id')))
            ->orderBy('id')->get();

    }

    private function resolve(Phase $phase, ParticipationContext $comparison): array
    {
        ['reads' => $reads, 'gates' => $gates, 'population' => $population, 'anchor' => $anchor] = $this->structuralPoolFor($phase, $comparison);
        if ($anchor === null || $reads->isEmpty()) {
            return ['reads' => collect(), 'gates' => collect()];
        }
        $field = $this->directions->forPopulation($anchor, $population, 'correspondence', $comparison);
        if ($field->direction === null) {
            // Only an entirely unmeasured field has no direction; zero uses hysteresis/default.
            // MediationReadService independently preserves the ordinary local path.
            return ['reads' => collect(), 'gates' => collect()];
        }
        $reads = $comparison->nodes($reads->pluck('id'))
            ->filter(function ($node) use ($comparison, $field): bool {
                $value = $field->values[$node->id] ?? null;
                if (! $comparison->activeIds->has($node->id) || $value === null) {
                    return false;
                }
                $direction = $value === 0.0
                    ? $this->measurements->correspondenceDirectionFor($node, $comparison)
                    : ObservableDirection::after($value);

                return $direction === $field->direction;
            })->keyBy('id');
        $parents = $comparison->edges->whereIn('source_proposition_id', $reads->keys())
            ->whereIn('target_proposition_id', $gates->pluck('id'))->groupBy('target_proposition_id');
        $seeds = $comparison->seedIds();
        $gates = $comparison->nodes($gates->pluck('id'))->filter(function ($gate) use ($comparison, $reads, $parents, $seeds): bool {
            if (! $this->availability->mutable($gate, $comparison)) {
                return false;
            }

            return $parents->get($gate->id, collect())->contains(function ($edge) use ($gate, $reads, $seeds): bool {
                $read = $reads[$edge->source_proposition_id];

                return ($seeds[$gate->id] ?? null) !== null
                    && $seeds[$gate->id] === ($seeds[$read->id] ?? null)
                    && $gate->phase->cycle->context_horizon_id === $read->phase->cycle->context_horizon_id;
            });
        })->sortBy('id')->values();

        return ['reads' => $reads->values(), 'gates' => $gates];
    }
}
