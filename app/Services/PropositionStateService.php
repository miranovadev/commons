<?php

namespace App\Services;

use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/**
 * Only Commit inherits contribution history, by reference to the original rows.
 * Supersession strength controls topology, never history inheritance.
 */
final class PropositionStateService
{
    /** Attach aggregate effective counts without replacing the viewer's own Attestation relation. */
    public function loadAttestationCounts(Collection $propositions, ?ParticipationContext $context = null): void
    {
        if ($context === null) {
            $this->loadCommitSources($propositions);
        }
        $sources = $propositions->unique('id')->mapWithKeys(fn (Proposition $item) => [
            $item->id => $item->tier === 'committal' ? $this->sourceIds($item, $context) : collect([$item->id]),
        ]);
        $counts = $context !== null
            ? $context->effectiveAttestations($sources->flatten()->unique())->groupBy('proposition_id')->map(fn ($group) => $group->groupBy('direction')->map(fn ($items, $direction) => (object) ['direction' => $direction, 'aggregate' => $items->count()]))
            : Attestation::query()->effective()
                ->whereIn('proposition_id', $sources->flatten()->unique())
                ->selectRaw('proposition_id, direction, count(*) as aggregate')
                ->groupBy('proposition_id', 'direction')->get()->groupBy('proposition_id');
        foreach ($propositions as $proposition) {
            $summary = ['support' => 0, 'refute' => 0];
            foreach ($sources[$proposition->id] as $id) {
                foreach ($counts->get($id, collect()) as $count) {
                    $summary[$count->direction] += (int) $count->aggregate;
                }
            }
            $proposition->setRelation('attestationCounts', collect($summary));
        }
    }

    /** @return Collection<int, string> */
    public function sourceIds(Proposition $position, ?ParticipationContext $context = null): Collection
    {
        if ($context !== null) {
            return $context->sourceIds($position);
        }
        $substrateId = $position->phase->cycle->participant->substrate_id
            ?? $position->phase->cycle->participant()->value('substrate_id');
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
                // Preview declares the same Commit source as the persisted transition.
                // Generic structural parents must never supply contribution history.
                $parents = ! $proposition->exists
                    ? $proposition->commitSources
                    : ($proposition->relationLoaded('commitSources') ? $proposition->commitSources : $proposition->commitSources()->get());
                $next = $next->concat($parents->filter(fn (Proposition $parent) => $parent->phase->cycle->participant->substrate_id === $substrateId));
            }
            $frontier = $next;
        }

        return $seen->unique()->values();
    }

    /** Batch history traversal by depth, rather than issuing queries per graph node. */
    private function loadCommitSources(Collection $propositions): void
    {
        $frontier = $propositions;
        $seen = [];
        while ($frontier->isNotEmpty()) {
            $commits = $frontier->filter(fn (Proposition $node) => $node->exists
                && $node->tier === 'committal' && ! isset($seen[$node->id]));
            if ($commits->isEmpty()) {
                break;
            }
            (new \Illuminate\Database\Eloquent\Collection($commits->all()))
                ->loadMissing('commitSources.phase.cycle.participant');
            foreach ($commits as $node) {
                $seen[$node->id] = true;
            }
            $frontier = $commits->flatMap(fn (Proposition $node) => $node->commitSources);
        }
    }

    /** Each persisted Phase is one observation, including the currently open Phase. */
    public function touchCount(Proposition $proposition, ?ParticipationContext $context = null): int
    {
        if ($context !== null) {
            return (int) $context->sourceIds($proposition)->sum(fn ($id) => $context->touches->get($id, 0));
        }

        return Phase::query()->whereIn('gate_proposition_id', $this->sourceIds($proposition))
            ->whereHas('cycle.participant', fn ($query) => $query->where(
                'substrate_id', $proposition->phase->cycle->participant->substrate_id,
            ))->count();
    }

    /** @param Collection<int, Proposition> $propositions */
    public function attestations(Collection $propositions, ?ParticipationContext $context = null): Collection
    {
        $ids = $propositions->flatMap(fn (Proposition $item) => $this->sourceIds($item, $context))->unique();

        return $context !== null ? $context->effectiveAttestations($ids) : Attestation::query()->effective()->whereIn('proposition_id', $ids)->get();
    }

    /** Completed direct or inherited evidence, deduplicated by its original Receipt ID. */
    public function receipts(Proposition $proposition, ?ParticipationContext $context = null): Collection
    {
        if ($context !== null) {
            $ids = $context->sourceIds($proposition);
            $attestations = $context->effectiveAttestations($ids)->pluck('id');

            return $context->receipts->filter(fn ($receipt) => $receipt->instrumentExecution?->status === 'completed'
                && (($receipt->receiptable_type === (new Proposition)->getMorphClass() && $ids->contains($receipt->receiptable_id))
                    || ($receipt->receiptable_type === (new Attestation)->getMorphClass() && $attestations->contains($receipt->receiptable_id))))->values();
        }
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
