<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use Illuminate\Support\Collection;

/** Commit inherits participation by reference; contribution identities never change. */
final class PropositionStateService
{
    /** @return Collection<int, string> */
    public function sourceIds(Proposition $position): Collection
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $frontier = collect([$position]);
        $seen = collect();
        while ($frontier->isNotEmpty()) {
            $next = collect();
            foreach ($frontier as $proposition) {
                if ($seen->contains($proposition->id)) {
                    continue;
                }
                $seen->push($proposition->id);
                if ($proposition->tier !== 'committal') {
                    continue;
                }
                // Prospective Commit has the same parent as the eventual persisted edge.
                $parents = ! $proposition->exists
                    ? $proposition->parents
                    : $proposition->parents()->wherePivot('type', 'commit')->get();
                $next = $next->concat($parents->filter(fn (Proposition $parent) =>
                    $parent->phase->cycle->participant->substrate_id === $substrateId));
            }
            $frontier = $next;
        }

        return $seen->unique()->values();
    }

    /** Each persisted Phase is one observation, including the currently open Phase. */
    public function touchCount(Proposition $proposition): int
    {
        return Phase::query()->whereIn('gate_proposition_id', $this->sourceIds($proposition))
            ->whereHas('cycle.participant', fn ($query) => $query->where(
                'substrate_id', $proposition->phase->cycle->participant->substrate_id,
            ))->count();
    }

    /** @param Collection<int, Proposition> $propositions */
    public function attestations(Collection $propositions): Collection
    {
        $ids = $propositions->flatMap(fn (Proposition $item) => $this->sourceIds($item))->unique();

        return Attestation::query()->effective()->whereIn('proposition_id', $ids)->get();
    }

    /** Completed direct or inherited evidence, deduplicated by its original Receipt ID. */
    public function receipts(Proposition $proposition): Collection
    {
        $ids = $this->sourceIds($proposition);
        $attestations = $this->attestations(collect([$proposition]))->pluck('id');

        return Receipt::query()
            ->whereHas('instrumentExecution', fn ($query) => $query->where('status', 'completed'))
            ->where(function ($query) use ($ids, $attestations): void {
                $query->where(fn ($query) => $query
                    ->where('receiptable_type', (new Proposition)->getMorphClass())
                    ->whereIn('receiptable_id', $ids))
                    ->orWhere(fn ($query) => $query
                        ->where('receiptable_type', (new Attestation)->getMorphClass())
                        ->whereIn('receiptable_id', $attestations));
            })->get();
    }
}
