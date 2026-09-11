<?php

namespace App\Services;

use App\Domain\Observables\AttestationTimeline;
use App\Enums\ObservableDirection as Direction;
use App\Enums\RegisterPosition;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\Observables\ReinforcementService;
use Illuminate\Support\Collection;

final readonly class AttestationEvidenceService
{
    public function __construct(
        private PropositionStateService $state,
        private CommitRegionService $regions,
        private CommitRegionPolarityService $polarity,
        private ReinforcementService $reinforcement,
    ) {}

    public function valueFor(Collection $positions, Substrate $substrate): float
    {
        $ids = $positions->flatMap(fn ($position) => $this->state->sourceIds($position))->unique()->all();
        return $this->timeline($ids, $substrate)->value($ids);
    }

    public function directionFor(Proposition $position, ?int $through = null): Direction
    {
        $seed = $this->regions->seedFor($position);
        $initial = $seed === null ? Direction::Positive : $this->polarity->forSeed($seed)['governing'];
        $ids = $this->state->sourceIds($position)->all();
        return $this->timeline($ids, $position->phase->cycle->participant->substrate)
            ->direction($initial, fn () => $ids, through: $through ?? PHP_INT_MAX);
    }

    /** Replay the same region/register's historical active population, not today's heads alone. */
    public function populationDirection(Proposition $seed, RegisterPosition $register): Direction
    {
        $members = $this->regions->membersFor($seed)->whereIn('register', $register->registers());
        $members->load('ledgerEntry');
        $edges = Supersession::query()->whereIn('source_proposition_id', $members->pluck('id'))
            ->where('type', '!=', 'variant')->with('ledgerEntry')->get();
        $removed = $edges->groupBy('source_proposition_id')->map(fn ($group) =>
            $group->min(fn ($edge) => $edge->ledgerEntry?->sequence ?? 0));
        $events = $members->map(fn ($node) => $node->ledgerEntry?->sequence ?? 0)
            ->merge($removed->values())->all();
        $timeline = $this->timeline($members->pluck('id')->all(), $seed->phase->cycle->participant->substrate);

        return $timeline->direction($this->polarity->forSeed($seed)['governing'],
            fn (int $sequence) => $members->filter(fn ($node) =>
                ($node->ledgerEntry?->sequence ?? 0) <= $sequence
                && (! $removed->has($node->id) || $removed[$node->id] > $sequence))->pluck('id')->all(),
            $events);
    }

    private function timeline(array $sourceIds, Substrate $substrate): AttestationTimeline
    {
        $attestations = Attestation::query()->whereIn('proposition_id', $sourceIds)
            ->with(['ledgerEntry', 'supersededBy.ledgerEntry', 'proposition.phase.cycle.participant'])->get();
        $receipts = Receipt::query()->where('receiptable_type', (new Attestation)->getMorphClass())
            ->whereIn('receiptable_id', $attestations->pluck('id'))
            ->where('type', '!=', 'citation')->whereNotNull('signature')->with('ledgerEntry')->get()->groupBy('receiptable_id');
        $frames = [];
        $rows = [];
        foreach ($attestations as $attestation) {
            if (! isset($frames[$attestation->proposition_id])) {
                $seed = $this->regions->seedFor($attestation->proposition);
                // Isolated legacy measurement fixtures have no region. They do not
                // become selectable; their numeric evidence keeps the bootstrap frame.
                $frames[$attestation->proposition_id] = $seed === null ? Direction::Positive
                    : $this->polarity->forSeed($seed)['support'];
            }
            $support = $frames[$attestation->proposition_id] === Direction::Positive ? 1 : -1;
            $rows[] = [
                'id' => $attestation->id, 'proposition_id' => $attestation->proposition_id,
                'sequence' => $attestation->ledgerEntry?->sequence ?? 0,
                'replaced_at' => $attestation->supersededBy === null ? null : ($attestation->supersededBy->ledgerEntry?->sequence ?? 0),
                'sign' => $attestation->direction === 'support' ? $support : -$support,
                'receipts' => $receipts->get($attestation->id, collect())->map(fn ($receipt) => $receipt->ledgerEntry?->sequence ?? 0)->all(),
            ];
        }
        return new AttestationTimeline($rows, $this->reinforcement->receiptIncrement($substrate));
    }
}
