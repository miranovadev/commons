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
        // The validated key partitions the same full structural population. Rows
        // without ledger membership are unmeasurable, not synthetic zero members.
        $populations = $positions->filter(fn ($node) => isset($keys[$node->id]))
            ->groupBy(fn ($node) => $keys[$node->id]);
        if ($populations->isEmpty()) {
            return;
        }
        $context = new ParticipationContext($populations->first()->first()->phase, $positions->pluck('id'), includeUnavailableAdditional: true);
        foreach ($populations as $key => $population) {
            $position = $population->first();
            $last = DB::table('chirality_population_observations')->where('population_key', $key)->latest('id')->first();
            $previous = $last === null ? $this->precedingDirection($position) : ObservableDirection::from($last->direction);
            $values = [];
            foreach ($population as $member) {
                $values[$member->id] = app(PropositionMeasurementService::class)->observeChirality($context->propositions->get($member->id) ?? $member, $context);
            }
            $observation = app(HomeostaticDirectionService::class)->aggregate($values, 'chirality', $previous);
            if ($observation->mean === null) {
                continue;
            }
            if ($last !== null && (float) $last->mean === $observation->mean && $last->direction === $observation->direction->value) {
                continue;
            }
            DB::table('chirality_population_observations')->insert(['substrate_id' => $substrateId,
                'population_key' => $key, 'mean' => $observation->mean, 'direction' => $observation->direction->value]);
        }
    }
}
