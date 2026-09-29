<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Observables\CommittalLineageService;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Mutation-boundary observations, independent of disposable projection publication. */
final readonly class ChiralityPopulationHistoryService
{
    public function __construct(
        private HomeostaticPopulationService $populations,
        private HomologousPositionService $homology,
        private CommittalLineageService $lineage,
    ) {}

    /** Structural identity does not change when members enter or measurements change. */
    public function key(Proposition $position, ?Proposition $owner = null, ?string $domain = null): ?string
    {
        return $this->homology->populationKey($position, $owner, $domain);
    }

    /** Read-only: never borrow a member's direction or fabricate a zero observation. */
    public function precedingDirection(Proposition $position): ObservableDirection
    {
        $key = $this->key($position);
        if ($key === null) {
            return ObservableDirection::defaultFor('chirality');
        }
        $direction = DB::table('chirality_population_observations')
            ->where('population_key', $key)->latest('id')->value('direction');
        if ($direction !== null) {
            return ObservableDirection::from($direction);
        }
        $horizon = $position->phase->cycle->contextHorizon;
        $ancestors = $this->lineage->ancestors($position);
        // Follow the actual entry lineage into successive preceding Horizons.
        // The boundary itself opens the current Horizon; its preceding entry owns
        // the corresponding expressive population in the preceding Horizon.
        while ($horizon->parent_context_horizon_id !== null) {
            $horizon = $horizon->parent;
            foreach ($ancestors as $ancestor) {
                $ownerHorizon = $ancestor->isClosureBoundary() ? $ancestor->opened_horizon_id : $ancestor->creating_horizon_id;
                if ($ownerHorizon !== $horizon->id) {
                    continue;
                }
                $owner = Proposition::findOrFail($ancestor->proposition_id);
                $key = $this->key($position, $owner, $horizon->domain->value);
                $direction = DB::table('chirality_population_observations')->where('population_key', $key)->latest('id')->value('direction');
                if ($direction !== null) {
                    return ObservableDirection::from($direction);
                }
            }
        }

        return ObservableDirection::defaultFor('chirality');
    }

    /** Capture both sides of a complete population-affecting mutation under the root lock. */
    public function observe(string $substrateId): void
    {
        if (DB::transactionLevel() === 0) {
            DB::transaction(fn () => $this->observe($substrateId));

            return;
        }
        Substrate::whereKey($substrateId)->lockForUpdate()->firstOrFail();
        $positions = Proposition::whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $substrateId))
            ->whereHas('ledgerEntry')->with('phase.cycle.contextHorizon', 'phase.cycle.participant', 'commitmentSnapshot')->get();
        $keys = $this->homology->populationKeys($positions);
        $seen = [];
        $context = null;
        foreach ($positions as $position) {
            $key = $keys[$position->id] ?? null;
            if ($key === null || isset($seen[$key])) {
                continue;
            }
            $population = $this->populations->forPosition($position);
            if (! $population->contains('id', $position->id)) {
                continue;
            }
            $seen[$key] = true;
            $context ??= new ParticipationContext($position->phase, $positions->pluck('id'), includeUnavailableAdditional: true);
            $values = [];
            foreach ($population as $member) {
                $values[$member->id] = app(PropositionMeasurementService::class)->observeChirality($context->propositions->get($member->id) ?? $member, $context);
            }
            $observation = app(HomeostaticDirectionService::class)->aggregate($values, 'chirality', $this->precedingDirection($position));
            if ($observation->mean === null) {
                continue;
            }
            $last = DB::table('chirality_population_observations')->where('population_key', $key)->latest('id')->first();
            if ($last !== null && (float) $last->mean === $observation->mean && $last->direction === $observation->direction->value) {
                continue;
            }
            DB::table('chirality_population_observations')->insert(['substrate_id' => $substrateId,
                'population_key' => $key, 'mean' => $observation->mean, 'direction' => $observation->direction->value]);
        }
    }
}
