<?php

namespace App\Services;

use App\Enums\SupersessionStrength;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;

/** A resolved participant path, never an ancestry or Horizon population. */
final readonly class ActiveParticipantLineage
{
    public function __construct(private CommitRegionService $regions) {}

    /**
     * Resolve before removing operational roles. Hard inputs are traversed for
     * provenance, but omitted from active history. The creating Phase records
     * which parent was the traversed Gate when a result has multiple inputs.
     * No count/token truncation may silently disconnect provenance from head.
     *
     * @return Collection<int, Proposition> Provenance first, current position last.
     */
    public function endingAt(Proposition $head, ?ParticipationContext $context = null): Collection
    {
        $seed = $this->regions->seedFor($head, $context);
        $bridges = collect();
        $path = $this->pathFrom($head, $context, bridges: $bridges);
        $boundary = $seed?->id;
        foreach ($bridges as [$owner, $sourceOwner]) {
            if ($boundary === $owner) {
                $boundary = $sourceOwner;
            }
        }
        if ($seed !== null && $path->first()?->id !== $boundary) {
            throw new DomainException('Participant lineage does not reach its governing provenance.');
        }

        return $path;
    }

    private function pathFrom(Proposition $head, ?ParticipationContext $context, array $seen = [], array $excluded = [], ?Collection $bridges = null): Collection
    {
        $path = collect();
        $node = $head;

        while ($node !== null) {
            if (isset($seen[$node->id])) {
                throw new DomainException('Participant lineage contains a structural cycle.');
            }
            $seen[$node->id] = true;
            $boundary = $node->tier === 'committal';
            $outgoing = $context?->children->get($node->id, collect())
                ?? Supersession::where('source_proposition_id', $node->id)->get();
            $hardSuperseded = $outgoing->contains(fn ($edge) => SupersessionStrength::for($edge->type) === SupersessionStrength::Hard);
            if ($boundary || (! isset($excluded[$node->id]) && ! $hardSuperseded)) {
                $path->prepend($node);
            }
            if ($boundary) {
                break;
            }

            $edges = $context?->parents->get($node->id, collect())
                ?? Supersession::where('target_proposition_id', $node->id)->get();
            if ($edges->isEmpty()) {
                break; // Legacy unseeded positions have no invented provenance.
            }
            foreach ($edges as $input) {
                if (SupersessionStrength::for($input->type) === SupersessionStrength::Hard) {
                    $excluded[$input->source_proposition_id] = true;
                }
            }
            $edge = $edges->count() === 1 ? $edges->first()
                : $edges->firstWhere('source_proposition_id', $node->phase->gate_proposition_id);
            if ($edge === null) {
                // Older/imported multi-parent records may lack the traversed
                // Gate. Expose only their shared resolved history, never choose
                // a sibling by ID or creation time.
                $hardInputs = $edges->filter(fn ($input) => SupersessionStrength::for($input->type) === SupersessionStrength::Hard)
                    ->pluck('source_proposition_id');
                $parents = $edges->map(fn ($input) => $context !== null
                    ? $context->propositions->get($input->source_proposition_id) : Proposition::find($input->source_proposition_id));
                if ($parents->contains(null)) {
                    return $path;
                }
                $histories = $parents->map(fn ($parent) => $this->pathFrom($parent, $context, $seen, $excluded)
                    ->whereNotIn('id', $hardInputs)->values());
                $shared = $histories->first()->filter(fn ($ancestor) => $histories->every(fn ($history) => $history->contains('id', $ancestor->id)));

                return $shared->concat($path)->values();
            }
            $parent = $context !== null ? $context->propositions->get($edge->source_proposition_id)
                : Proposition::find($edge->source_proposition_id);
            if ($bridges !== null && $parent !== null && $node->phase->cycle->entry_committal_id !== $parent->phase->cycle->entry_committal_id
                && app(RecordedBiasSourceService::class)->recordedSourceFor($node, $edges, $context)?->id === $parent->id) {
                // Accounting stays at the creating Cycle entry. A recorded unary
                // Bias transition follows its actual selected source's provenance.
                $bridges->push([$node->phase->cycle->entry_committal_id, $parent->phase->cycle->entry_committal_id]);
            }
            $node = $parent;
        }

        return $path->values();
    }
}
