<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Additional Bias sources only; structural recognition grants no movement or evidence scope. */
final readonly class HomologousBiasService
{
    public function __construct(
        private HomeostaticPopulationService $populations,
        private HomeostaticDirectionService $directions,
        private PhasePositionService $positions,
        private PropositionAvailabilityService $availability,
    ) {}

    /** Pre-hydration discovery must not construct a ParticipationContext. */
    public function populationFor(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $discover = function () use ($phase): Collection {
            $gate = $phase->gate()->first();
            if ($phase->kind !== PhaseKind::Bias || $gate === null
                || ! $this->positions->acceptsGate($phase, $gate)) {
                return collect();
            }

            return $this->populations->forPosition($gate);
        };

        return $context === null ? $discover() : $context->remember('homologous-bias-structure:'.$phase->id, $discover);
    }

    /** Dominant matching is additional eligibility; local Bias is evaluated independently. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        if ($phase->kind !== PhaseKind::Bias) {
            return collect();
        }
        $context ??= new ParticipationContext($phase);

        return $context->remember('homologous-bias:'.$phase->id, function () use ($phase, $context): Collection {
            $population = $this->populationFor($phase, $context);
            $gate = $population->firstWhere('id', $phase->gate_proposition_id);
            if ($gate === null) {
                return collect();
            }
            $field = $this->directions->forPopulation($gate, $population, 'chirality', $context);
            if ($field->direction === null) {
                return collect(); // No measured members, rather than a numeric zero.
            }
            $candidates = $context->nodes($population->pluck('id'))->where('id', '!=', $gate->id)
                ->filter(function ($node) use ($context, $field): bool {
                    $value = $field->values[$node->id] ?? null;

                    // Numeric Chirality is not the regional Attestation/Bias lean.
                    // No persisted numeric member history exists; zero initializes explicitly.
                    return $value !== null && $this->availability->mutable($node, $context)
                        && ObservableDirection::after($value, ObservableDirection::defaultFor('chirality')) === $field->direction;
                });
            $horizons = $candidates->map(fn ($node) => $node->phase->cycle->context_horizon_id)
                ->reject(fn ($id) => $id === $phase->cycle->context_horizon_id)->unique()->sort()->values();
            // A comparison subset cannot prove exhaustion in another Horizon.
            $exhausted = app(ExhaustedContinuationService::class)->ids($phase, $context)
                ->union(app(ExhaustedContinuationService::class)->idsForHorizons($horizons));

            $spent = app(ExplorationEpochService::class)->currentFailuresForHorizons(
                $horizons->push($phase->cycle->context_horizon_id)->unique(), $candidates->pluck('id'), $context,
            );

            return $candidates->reject(fn ($node) => $exhausted->has($node->id) || $spent->has($node->id))->sortBy('id')->values();
        });
    }

    /** Recognize an already recorded action, without reapplying today's population direction. */
    public function recordedSourceFor(Proposition $target, Collection $edges, ?ParticipationContext $context = null): ?Proposition
    {
        $phase = $target->phase;
        if ($target->tier !== 'expressive' || $phase->kind !== PhaseKind::Bias || $edges->count() !== 1) {
            return null;
        }
        $edge = $edges->first();
        if ($edge->type !== 'reclassify' || $edge->phase_id !== $phase->id
            || $edge->target_proposition_id !== $target->id || $edge->source_proposition_id === $phase->gate_proposition_id) {
            return null;
        }
        $records = $context?->classifications ?? DB::table('reclassification_results')->where('target_proposition_id', $target->id)->get();
        if (! $records->contains(fn ($record) => $record->source_proposition_id === $edge->source_proposition_id
            && $record->target_proposition_id === $target->id && $record->register === $target->register)) {
            return null;
        }
        $source = $context !== null ? $context->propositions->get($edge->source_proposition_id)
            : Proposition::find($edge->source_proposition_id);

        return $source?->tier === 'expressive' && $source->phase->kind === PhaseKind::Mediate
            && $source->phase->cycle->participant->substrate_id === $phase->cycle->participant->substrate_id ? $source : null;
    }

    public function foreignGateIdsFor(Phase $phase, ParticipationContext $context): Collection
    {
        $seeds = $context->seedIds();

        return $this->forPhase($phase, $context)
            ->filter(fn ($node) => ($seeds[$node->id] ?? null) !== $phase->cycle->entry_committal_id)->pluck('id');
    }
}
