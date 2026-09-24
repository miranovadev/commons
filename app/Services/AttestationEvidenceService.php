<?php

namespace App\Services;

use App\Domain\Observables\AttestationTimeline;
use App\Enums\ObservableDirection as Direction;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\ReceiptContextEvent;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class AttestationEvidenceService
{
    public function __construct(
        private PropositionStateService $state,
        private CommitRegionService $regions,
        private CommitRegionPolarityService $polarity,
        private ReinforcementService $reinforcement,
    ) {}

    public function valueFor(Collection $positions, Substrate $substrate, ?ParticipationContext $context = null): float
    {
        $ids = $positions->flatMap(fn ($position) => $this->state->sourceIds($position, $context))->unique()->all();

        return $this->timeline($ids, $substrate, $context)->value($ids);
    }

    public function directionFor(Proposition $position, ?int $through = null, ?ParticipationContext $context = null): Direction
    {
        $seed = $this->regions->seedFor($position, $context);
        $initial = $seed === null ? Direction::Positive : $this->polarity->forSeed($seed, $context)['governing'];
        $ids = $this->state->sourceIds($position, $context)->all();

        return $this->timeline($ids, $context?->substrate ?? $position->phase->cycle->participant->substrate, $context)
            ->direction($initial, fn () => $ids, through: $through ?? PHP_INT_MAX);
    }

    /** Causal ancestors only: both reconciliation parents, one copy of each judgment. */
    public function lineageDirectionFor(Proposition $candidate, ?ParticipationContext $context = null): Direction
    {
        $seed = $this->regions->seedFor($candidate, $context);
        $initial = $seed === null ? Direction::Positive : $this->polarity->forSeed($seed, $context)['governing'];
        $ids = $this->lineageSourceIds($candidate, $context)->all();

        return $this->timeline($ids, $context?->substrate ?? $candidate->phase->cycle->participant->substrate, $context)
            ->direction($initial, fn () => $ids);
    }

    /** Superseded ancestors supply historical context, not another active reinforcement copy. */
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
        $timeline = $this->timeline($members->pluck('id')->all(), $context?->substrate ?? $seed->phase->cycle->participant->substrate, $context);

        return $timeline->direction($this->polarity->forSeed($seed, $context)['governing'],
            fn (int $sequence) => $members->filter(fn ($node) => ($node->ledgerEntry?->sequence ?? 0) <= $sequence
                && (! $removed->has($node->id) || $removed[$node->id] > $sequence))->pluck('id')->all(),
            $events);
    }

    private function timeline(array $sourceIds, Substrate $substrate, ?ParticipationContext $context = null): AttestationTimeline
    {
        if ($context !== null) {
            sort($sourceIds);

            return $context->remember('timeline:'.implode(',', $sourceIds), fn () => $this->buildTimeline($sourceIds, $substrate, $context));
        }

        return $this->buildTimeline($sourceIds, $substrate);
    }

    private function buildTimeline(array $sourceIds, Substrate $substrate, ?ParticipationContext $context = null): AttestationTimeline
    {
        $attestations = $context !== null ? $context->attestationsFor($sourceIds) : Attestation::query()->whereIn('proposition_id', $sourceIds)
            ->with(['ledgerEntry', 'supersededBy.ledgerEntry', 'proposition.phase.cycle.participant', 'phase.cycle'])->get();
        $links = $context !== null ? $context->receiptLinks->whereIn('proposition_id', $sourceIds)
            : DB::table('proposition_receipt_links')->whereIn('proposition_id', $sourceIds)->get();
        $events = $context !== null ? $context->receiptContextEvents : ReceiptContextEvent::whereHas('context', fn ($q) => $q->whereIn('proposition_id', $sourceIds)->orWhereIn('receipt_id', $links->pluck('receipt_id')))
            ->with(['ledgerEntry', 'context.receipt'])->get();
        $loads = [];
        $contexts = $context?->receiptContexts->keyBy('id');
        $receipts = $context?->receipts->keyBy('id');
        foreach ($events as $event) {
            $load = $context !== null ? $contexts->get($event->participant_receipt_context_id) : $event->context;
            $receipt = $context !== null ? $receipts->get($load->receipt_id) : $load->receipt;
            if ($receipt === null || $receipt->type === 'tragedy' || $load->evidence_signature === null) {
                continue;
            }
            // Context rows already resolve legacy Attestation subjects to their Proposition.
            $targets = $links->where('receipt_id', $receipt->id)->pluck('proposition_id')->push($load->proposition_id)->unique();
            foreach ($targets as $target) {
                $loads[$target][$load->participant_id][] = [
                    'id' => $load->id, 'participant_id' => $load->participant_id,
                    'sequence' => $event->ledgerEntry?->sequence ?? 0, 'action' => $event->action,
                ];
            }
        }

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
                'loads' => $loads[$attestation->proposition_id][$attestation->phase->cycle->participant_id] ?? [],
            ];
        }

        return new AttestationTimeline($rows, $this->reinforcement->receiptIncrement($substrate));
    }
}
