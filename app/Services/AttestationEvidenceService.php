<?php

namespace App\Services;

use App\Domain\Observables\AttestationTimeline;
use App\Enums\ObservableDirection as Direction;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class AttestationEvidenceService
{
    public function __construct(
        private PropositionStateService $state,
        private CommitRegionService $regions,
        private CommitRegionPolarityService $polarity,
    ) {}

    public function valueFor(Collection $positions, ?ParticipationContext $context = null): float
    {
        $ids = $positions->flatMap(fn ($position) => $this->state->sourceIds($position, $context))->unique()->all();

        return $this->timeline($ids, $context)->value($ids);
    }

    public function directionFor(Proposition $position, ?int $through = null, ?ParticipationContext $context = null): Direction
    {
        $seed = $this->regions->seedFor($position, $context);
        $initial = $seed === null ? Direction::Positive : $this->polarity->forSeed($seed, $context)['governing'];
        $ids = $this->state->sourceIds($position, $context)->all();

        return $this->timeline($ids, $context)
            ->direction($initial, fn () => $ids, through: $through ?? PHP_INT_MAX);
    }

    /** Causal ancestors only: both reconciliation parents, one copy of each judgment. */
    public function lineageDirectionFor(Proposition $candidate, ?ParticipationContext $context = null): Direction
    {
        $seed = $this->regions->seedFor($candidate, $context);
        $initial = $seed === null ? Direction::Positive : $this->polarity->forSeed($seed, $context)['governing'];
        $ids = $this->lineageSourceIds($candidate, $context)->all();

        return $this->timeline($ids, $context)
            ->direction($initial, fn () => $ids);
    }

    /** Superseded ancestors supply historical context, not another effective Attestation copy. */
    public function lineageSourceIds(Proposition $candidate, ?ParticipationContext $context = null): Collection
    {
        $seen = [$candidate->id => true];
        $ids = [];
        $frontier = collect([$candidate]);
        $substrateId = $candidate->phase->cycle->participant->substrate_id;
        while ($frontier->isNotEmpty()) {
            $parents = $context !== null
                ? $frontier->flatMap(fn ($node) => $context->parentNodes($node))->unique('id')
                : Proposition::query()->whereKey(Supersession::whereIn('target_proposition_id', $frontier->pluck('id'))->pluck('source_proposition_id'))
                    ->whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $substrateId))->get();
            $frontier = $parents->reject(fn ($node) => isset($seen[$node->id]) || $node->tier !== 'expressive')->values();
            foreach ($parents as $node) {
                $seen[$node->id] = true;
            }
            foreach ($frontier as $node) {
                $ids[] = $node->id;
            }
        }

        return collect($ids);
    }

    /** Replay the same region/register's historical active population, not today's heads alone. */
    public function populationDirection(Proposition $seed, string $register, ?ParticipationContext $context = null): Direction
    {
        $members = $this->regions->membersFor($seed, $context)->where('register', $register);
        if ($context === null) {
            $members->load('ledgerEntry');
        }
        $edges = $context !== null ? $context->edges->whereIn('source_proposition_id', $members->pluck('id'))->where('type', '!=', 'variant') : Supersession::query()->whereIn('source_proposition_id', $members->pluck('id'))
            ->where('type', '!=', 'variant')->with('ledgerEntry')->get();
        $removed = $edges->groupBy('source_proposition_id')->map(fn ($group) => $group->min(fn ($edge) => $edge->ledgerEntry?->sequence ?? 0));
        $events = $members->map(fn ($node) => $node->ledgerEntry?->sequence ?? 0)
            ->merge($removed->values())->all();
        $timeline = $this->timeline($members->pluck('id')->all(), $context);

        return $timeline->direction($this->polarity->forSeed($seed, $context)['governing'],
            fn (int $sequence) => $members->filter(fn ($node) => ($node->ledgerEntry?->sequence ?? 0) <= $sequence
                && (! $removed->has($node->id) || $removed[$node->id] > $sequence))->pluck('id')->all(),
            $events);
    }

    private function timeline(array $sourceIds, ?ParticipationContext $context = null): AttestationTimeline
    {
        if ($context !== null) {
            sort($sourceIds);

            return $context->remember('timeline:'.implode(',', $sourceIds), fn () => $this->buildTimeline($sourceIds, $context));
        }

        return $this->buildTimeline($sourceIds);
    }

    private function buildTimeline(array $sourceIds, ?ParticipationContext $context = null): AttestationTimeline
    {
        $attestations = $context !== null ? $context->attestationsFor($sourceIds) : Attestation::query()->whereIn('proposition_id', $sourceIds)
            ->with(['ledgerEntry', 'supersededBy.ledgerEntry', 'proposition.phase.cycle.participant', 'phase.cycle'])->get();
        $frames = [];
        $rows = [];
        foreach ($attestations as $attestation) {
            if (! isset($frames[$attestation->proposition_id])) {
                $seed = $this->regions->seedFor($attestation->proposition, $context);
                // Isolated legacy measurement fixtures have no region. They do not
                // become selectable; their numeric evidence keeps the bootstrap frame.
                $frames[$attestation->proposition_id] = $seed === null ? Direction::Positive
                    : $this->polarity->forSeed($seed, $context)['support'];
            }
            $support = $frames[$attestation->proposition_id] === Direction::Positive ? 1 : -1;
            $rows[] = [
                'id' => $attestation->id, 'proposition_id' => $attestation->proposition_id,
                'sequence' => $attestation->ledgerEntry?->sequence ?? 0,
                'replaced_at' => $attestation->supersededBy === null ? null : ($attestation->supersededBy->ledgerEntry?->sequence ?? 0),
                'sign' => $attestation->direction === 'support' ? $support : -$support,
            ];
        }

        return new AttestationTimeline($rows);
    }
}
