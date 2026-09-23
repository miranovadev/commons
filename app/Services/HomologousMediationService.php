<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Foreign Mediate comparison pool; final action policy stays with reconciliation. */
final readonly class HomologousMediationService
{
    public function __construct(
        private HomologousPositionService $homology,
        private MediationAnchorService $mediation,
        private PhasePositionService $positions,
        private CommitRegionService $regions,
        private PropositionMeasurementService $measurements,
        private PropositionAvailabilityService $availability,
    ) {}

    /**
     * Retained direction belongs to the causal Constrain Read, never its Gate child.
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

    /** Pre-hydration discovery never constructs a ParticipationContext. */
    public function structuralPoolFor(Phase $phase, ?ParticipationContext $context = null): array
    {
        return $context === null ? $this->discover($phase)
            : $context->remember('homologous-mediate-structure:'.$phase->id, fn () => $this->discover($phase));
    }

    private function discover(Phase $phase): array
    {
        $empty = ['reads' => collect(), 'gates' => collect(), 'anchor' => null];
        $anchor = $this->mediation->anchorFor($phase);
        if ($anchor === null || ! $this->positions->matches($anchor, $phase, PhaseKind::Constrain)) {
            return $empty;
        }
        $owner = $this->regions->seedFor($anchor);
        if ($owner === null || $owner->id !== $phase->cycle->governing_committal_id) {
            return $empty;
        }
        $reads = $this->homology->candidatesFor($anchor, PhaseKind::Constrain, foreignRegionsOnly: true);
        $owners = $this->regions->seedIdsFor($reads, $reads);
        $reads = $reads->filter(fn ($node) => ($owners[$node->id] ?? null) !== null
            && $owners[$node->id] !== $owner->id)->values();
        if ($reads->isEmpty()) {
            return $empty;
        }

        $domain = $phase->cycle->contextHorizon->domain;
        $register = $domain->registerFor(PhaseKind::Disperse);
        $gates = Proposition::query()->active()->where('tier', 'expressive')->where('register', $register)
            ->whereHas('phase', fn ($q) => $q->where('kind', PhaseKind::Disperse->value)->where('write_register', $register)
                ->whereHas('cycle', fn ($q) => $q
                    ->whereHas('contextHorizon', fn ($q) => $q->where('domain', $domain->value))
                    ->whereHas('participant', fn ($q) => $q->where('substrate_id', $phase->cycle->participant->substrate_id))))
            ->whereHas('parents', fn ($q) => $q->whereKey($reads->pluck('id')))
            ->orderBy('id')->get();

        return ['reads' => $reads, 'gates' => $gates, 'anchor' => $anchor];
    }

    private function resolve(Phase $phase, ParticipationContext $comparison): array
    {
        ['reads' => $reads, 'gates' => $gates, 'anchor' => $anchor] = $this->structuralPoolFor($phase, $comparison);
        if ($anchor === null || $reads->isEmpty()) {
            return ['reads' => collect(), 'gates' => collect()];
        }
        $direction = $this->measurements->correspondenceDirectionFor($anchor, $comparison);
        $reads = $comparison->nodes($reads->pluck('id'))
            ->filter(fn ($node) => $comparison->activeIds->has($node->id)
                && $this->measurements->correspondenceDirectionFor($node, $comparison) === $direction)
            ->keyBy('id');
        $parents = Supersession::whereIn('source_proposition_id', $reads->keys())
            ->whereIn('target_proposition_id', $gates->pluck('id'))->get()->groupBy('target_proposition_id');
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
