<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Enums\RegisterPosition;
use Illuminate\Support\Collection;

final readonly class MediationReadService
{
    public function __construct(
        private PropositionMeasurementService $measurements,
        private CommitRegionService $regions,
    ) {}

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

    /** Anchor first, then corresponding Reads across the Commit Region. */
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

        $siblings = $this->regions->forPhase($phase, $anchor)->where('id', '!=', $anchor->id);
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

        $pool = $this->regions->atPosition($phase, $gate, RegisterPosition::First);

        return Proposition::query()->whereKey($pool->pluck('id'))
            ->whereHas('parents', fn ($query) => $query->whereKey($reads->pluck('id')))
            ->orderBy('id')->get();
    }

    private function direction(float|string|null $value): int
    {
        return $value === null ? 0 : ((float) $value <=> 0.0);
    }
}

