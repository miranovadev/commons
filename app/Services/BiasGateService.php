<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ChiralityCalculator;
use Illuminate\Support\Collection;

final readonly class BiasGateService
{
    public function __construct(
        private LineageService $lineage,
        private ChiralityCalculator $chirality,
    ) {}

    /** Active Stances/Frontiers in the current forward families, counted once. */
    public function populationFor(Phase $phase): Collection
    {
        if ($phase->kind !== PhaseKind::Bias) {
            return collect();
        }
        $register = match ($phase->write_register) {
            'concord' => 'stance',
            'canon' => 'frontier',
            default => null,
        };
        $gate = $phase->gate()->with('contextHorizonMembership')->firstOrFail();
        $horizonId = $gate->contextHorizonMembership?->context_horizon_id;
        $substrateId = $phase->cycle->participant->substrate_id;
        if ($register === null || $gate->register !== $register || $gate->tier !== 'expressive'
            || $horizonId === null || $gate->phase->cycle->participant->substrate_id !== $substrateId) {
            return collect();
        }

        // Preserve the existing forward-family/Horizon boundary, without the
        // old immediate-sibling restriction or separate per-parent directions.
        $family = collect([$gate])->concat($this->lineage->descendantsInHorizon($gate, $horizonId));
        foreach ($gate->parents()->whereHas('phase.cycle.participant', fn ($query) => $query
            ->where('substrate_id', $substrateId))->get() as $parent) {
            $family = $family->concat($this->lineage->descendantsInHorizon($parent, $horizonId));
        }

        return Proposition::query()->whereKey($family->pluck('id')->unique())
            ->where('tier', 'expressive')->where('register', $register)
            ->whereDoesntHave('children', fn ($query) => $query->where('supersessions.type', '!=', 'variant'))
            ->orderBy('id')->get();
    }

    /** One receipt-weighted effective lean; no projected sign or extra attenuation. */
    public function leanFor(Phase $phase): float
    {
        return $this->chirality->leanFor($this->populationFor($phase)->pluck('id'), $phase->cycle->participant->substrate);
    }

    /** The retained field direction admits every matching active source. */
    public function forPhase(Phase $phase): Collection
    {
        $population = $this->populationFor($phase);
        $substrate = $phase->cycle->participant->substrate;
        $gate = $phase->gate()->firstOrFail();
        $direction = ObservableDirection::after(
            $this->chirality->leanFor($population->pluck('id'), $substrate),
            $this->chirality->directionFor($gate),
        );

        return $population->filter(fn (Proposition $candidate): bool =>
            $this->chirality->directionFor($candidate) === $direction)->values();
    }
}
