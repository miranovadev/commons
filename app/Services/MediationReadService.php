<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class MediationReadService
{
    public function __construct(private PropositionMeasurementService $measurements) {}

    /** The previous Disperse Gate is the anchor, regardless of display ordering. */
    public function anchorFor(Phase $phase): ?Proposition
    {
        if ($phase->kind !== PhaseKind::Mediate) {
            return null;
        }

        $disperse = $phase->cycle->phases()
            ->where('kind', PhaseKind::Disperse->value)
            ->where('id', '<', $phase->id)
            ->whereNotNull('completed_at')->latest('id')->first();
        $anchor = $disperse?->gate;

        return $anchor?->phase->cycle->participant->substrate_id === $phase->cycle->participant->substrate_id
            ? $anchor : null;
    }

    /** Anchor first, then matching immediate same-register siblings. No recursive expansion. */
    public function readsFor(Phase $phase): Collection
    {
        $anchor = $this->anchorFor($phase);
        if ($anchor === null) {
            return collect();
        }
        $reads = collect([$anchor]);
        $this->measurements->repairMissing($reads);
        $direction = $this->direction($anchor->measurement?->correspondence);
        if ($direction === 0) {
            return $reads;
        }

        $parentIds = $anchor->parents()->whereHas('phase.cycle.participant', fn ($query) => $query
            ->where('substrate_id', $phase->cycle->participant->substrate_id))->pluck('propositions.id');
        $siblings = $this->inContext($phase, $anchor)
            ->where('register', $anchor->register)->whereKeyNot($anchor->id)
            ->whereHas('parents', fn ($query) => $query->whereKey($parentIds))
            ->whereDoesntHave('children', fn ($query) => $query->where('supersessions.type', '!=', 'variant'))
            ->orderBy('id')->get();
        $this->measurements->repairMissing($siblings);

        return $reads->concat($siblings->filter(fn (Proposition $sibling): bool =>
            $this->direction($sibling->measurement?->correspondence) === $direction))->values();
    }

    /** All active immediate Gate children of the exposed Reads; no Gate sign/lean filter. */
    public function gatesFor(Phase $phase): Collection
    {
        $reads = $this->readsFor($phase);
        if ($reads->isEmpty()) {
            return collect();
        }
        $gate = $phase->gate()->firstOrFail();

        return $this->inContext($phase, $gate)
            ->where('tier', 'expressive')->where('register', $gate->register)
            ->whereHas('parents', fn ($query) => $query->whereKey($reads->pluck('id')))
            ->whereDoesntHave('children', fn ($query) => $query->where('supersessions.type', '!=', 'variant'))
            ->orderBy('id')->get();
    }

    private function inContext(Phase $phase, Proposition $position): Builder
    {
        return Proposition::query()
            ->whereHas('phase.cycle.participant', fn ($query) => $query
                ->where('substrate_id', $phase->cycle->participant->substrate_id))
            ->whereHas('contextHorizonMembership', fn ($query) => $query
                ->where('context_horizon_id', $position->contextHorizonMembership?->context_horizon_id));
    }

    private function direction(float|string|null $value): int
    {
        return $value === null ? 0 : ((float) $value <=> 0.0);
    }
}
