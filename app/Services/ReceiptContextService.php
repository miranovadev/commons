<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Enums\ReceiptCapability;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptContextEvent;
use App\Models\Substrate;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ReceiptContextService
{
    public function __construct(
        private ActiveParticipantLineage $lineage,
        private PropositionOptionService $options,
        private MediationReadService $mediation,
        private PropositionStateService $state,
        private ReceiptEvidenceService $evidence,
        private PropositionMeasurementService $measurements,
    ) {}

    /** Current Commit Region evidence is shared; exposed ancestor evidence remains contextual. */
    public function forPhase(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $context ??= new ParticipationContext($phase);

        return $context->remember('receipt-context', function () use ($phase, $context): Collection {
            $gate = $context->propositions->get($phase->gate_proposition_id);
            $foreignOperands = match ($phase->kind) {
                PhaseKind::Mediate => app(HomologousMediationService::class)->foreignGateIdsFor($phase, $context),
                PhaseKind::Bias => app(HomologousBiasService::class)->foreignGateIdsFor($phase, $context),
                default => collect(),
            };
            $exposed = $gate === null ? collect() : $this->lineage->endingAt($gate, $context)->concat([$gate])
                ->concat($this->mediation->visibleReadsFor($phase, $context))
                ->concat($this->options->gateOptionsFor($phase, $context)->whereNotIn('id', $foreignOperands))
                ->concat($this->options->writeOptionsFor($phase, $context))
                ->concat($this->options->instrumentOptionsFor($phase, $context))
                ->unique('id')->filter(fn ($node) => $context->activeIds->has($node->id));
            $present = $exposed->pluck('id');
            $available = app(HorizonReceiptService::class)->available($phase, $context);
            $receipts = $available
                ->concat($context->receipts->where('context_horizon_id', $phase->cycle->context_horizon_id)
                    ->whereIn('id', $context->receiptContexts->where('participant_id', $phase->cycle->participant_id)->whereNull('cleared_at')->pluck('receipt_id')))
                ->concat($exposed->flatMap(fn ($node) => $this->state->receipts($node, $context)))->unique('id');
            (new \Illuminate\Database\Eloquent\Collection($receipts->all()))->loadMissing(['consumption', 'phase', 'instrumentExecution']);
            $loads = ParticipantReceiptContext::where('participant_id', $phase->cycle->participant_id)
                ->whereIn('receipt_id', $receipts->pluck('id'))->get()->keyBy('receipt_id');
            if ($receipts->contains('type', 'citation')) {
                $present = $present->merge($this->evidence->loadedCitationTargets($context));
            }

            $reads = $phase->kind === PhaseKind::Order
                ? $this->options->orderReadsFor($phase, $context) : $this->mediation->visibleReadsFor($phase, $context);
            $activeIds = $reads->flatMap(fn ($node) => $this->state->receipts($node, $context)->pluck('id'))->unique();

            $replayTargets = $gate !== null && in_array('instrument.execute', config("phases.{$phase->kind->value}.actions", []), true)
                ? $this->options->instrumentOptionsFor($phase, $context)->pluck('id') : collect();

            return $receipts->map(function ($receipt) use ($context, $loads, $present, $replayTargets, $available, $activeIds): array {
                $verified = $this->evidence->describe($receipt, $context);
                $load = $loads->get($receipt->id);
                $loaded = $load !== null && $load->cleared_at === null;
                $alreadyPresent = isset($verified['referenced_proposition_id']) && $present->contains($verified['referenced_proposition_id']);
                $status = $loaded ? 'loaded' : ($verified === null ? 'unqualified' : ($alreadyPresent ? 'already-in-context' : 'available'));

                return [
                    'id' => $receipt->id, 'proposition_id' => $receipt->instrumentExecution?->proposition_id ?? $receipt->phase->gate_proposition_id,
                    'context_horizon_id' => $receipt->context_horizon_id,
                    'availability' => $receipt->consumption !== null ? 'consumed' :
                        ($available->contains('id', $receipt->id) ? 'available' : 'historical'),
                    'consumed_at' => $receipt->consumption?->consumed_at?->toISOString(),
                    'attached_proposition_id' => $receipt->consumption?->proposition_id,
                    'type' => $receipt->type, 'reference' => $receipt->reference,
                    'capabilities' => [
                        'readable' => $receipt->supports(ReceiptCapability::Readable),
                        'replayable' => $receipt->supports(ReceiptCapability::Replayable),
                    ],
                    'can_replay' => $receipt->supports(ReceiptCapability::Replayable)
                        && $replayTargets->contains($receipt->instrumentExecution->proposition_id)
                        && app(RelevantInstrumentService::class)->equivalent(
                            $context->propositions->get($receipt->instrumentExecution->proposition_id),
                            $receipt->replayInvocation(), $context,
                        ) === null,
                    'replayed_from_receipt_id' => $receipt->instrumentExecution?->replayed_from_receipt_id,
                    'observation_error' => $receipt->metadata['observation_error'] ?? null,
                    'signature' => $verified['signature'] ?? null,
                    'loaded' => $loaded, 'active' => $loaded && $activeIds->contains($receipt->id),
                    'status' => $status, 'loaded_at' => $loaded ? $load->loaded_at->toISOString() : null,
                    ...($verified !== null ? ['evidence' => $verified['evidence']] : []),
                ];
            })->sortBy('id')->values();
        });
    }

    /** Domain callers and the action dispatcher share the same encounter/lineage guard. */
    public function read(Participant $participant, Phase $phase, string $receiptId): ParticipantReceiptContext
    {
        return DB::transaction(function () use ($participant, $phase, $receiptId): ParticipantReceiptContext {
            $phase = $this->lock($participant, $phase);
            $context = new ParticipationContext($phase->fresh());
            $option = $this->forPhase($phase, $context)->firstWhere('id', $receiptId);
            if ($option === null || ! $option['capabilities']['readable'] || ! in_array($option['status'], ['available', 'loaded'], true)) {
                throw new DomainException('Read requires qualifying encountered evidence outside the current context.');
            }
            $load = ParticipantReceiptContext::where('participant_id', $participant->id)->where('receipt_id', $receiptId)->first();
            if ($load !== null && $load->cleared_at === null) {
                return $load;
            }
            $load ??= new ParticipantReceiptContext;
            $load->forceFill([
                'participant_id' => $participant->id, 'receipt_id' => $receiptId,
                'proposition_id' => $option['proposition_id'], 'evidence_signature' => $option['signature'],
                'loaded_at' => now(), 'cleared_at' => null,
            ])->save();
            $this->changed($load, $phase, 'loaded');

            return $load;
        });
    }

    /** Current causal Gate only; reconciliation operands are not this Gate. */
    public function gateOptions(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $context ??= new ParticipationContext($phase);

        return $context->remember('gate-receipt-options', function () use ($phase, $context): Collection {
            $gate = $context->propositions->get($phase->gate_proposition_id);
            if ($gate === null) {
                return collect();
            }
            $references = $this->state->receipts($gate, $context)->filter(fn ($receipt) => $receipt->type !== 'tragedy'
                && app(ReceiptRegionService::class)->contains($receipt, $phase, $context))->pluck('id');

            return $this->forPhase($phase, $context)->whereIn('id', $references)
                ->filter(fn ($item) => $item['capabilities']['readable'] && $item['signature'] !== null)->values();
        });
    }

    /** Caller owns the same transaction as the accepted normal Phase contribution. */
    public function desired(Participant $participant, Phase $phase, array $options): void
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Receipt options require the Phase contribution transaction.');
        }
        $this->lock($participant, $phase);
        if (array_diff(array_keys($options), ['load', 'unload']) !== []) {
            throw new DomainException('Receipt options accept only load and unload.');
        }
        $loadIds = $options['load'] ?? [];
        $unloadIds = $options['unload'] ?? [];
        if (! is_array($loadIds) || ! array_is_list($loadIds) || ! is_array($unloadIds) || ! array_is_list($unloadIds)
            || count(array_filter([...$loadIds, ...$unloadIds], 'is_string')) !== count($loadIds) + count($unloadIds)
            || array_intersect($loadIds, $unloadIds) !== []) {
            throw new DomainException('Receipt desired states must be disjoint lists of IDs.');
        }
        $eligible = $this->gateOptions($phase)->keyBy('id');
        if (array_diff([...$loadIds, ...$unloadIds], $eligible->keys()->all()) !== []) {
            throw new DomainException('Receipt options require qualifying references on the current Gate in this Commit Region.');
        }
        foreach (array_unique($loadIds) as $id) {
            $option = $eligible[$id];
            $load = ParticipantReceiptContext::where('participant_id', $participant->id)->where('receipt_id', $id)->first();
            if ($load !== null && $load->cleared_at === null) {
                continue;
            }
            $load ??= new ParticipantReceiptContext;
            $load->forceFill(['participant_id' => $participant->id, 'receipt_id' => $id,
                'proposition_id' => $option['proposition_id'], 'evidence_signature' => $option['signature'],
                'loaded_at' => now(), 'cleared_at' => null])->save();
            $this->changed($load, $phase, 'loaded');
        }
        foreach (array_unique($unloadIds) as $id) {
            $this->clear($participant, $phase, $id);
        }
    }

    /** Internal desired-state primitive; public options are Gate-validated above. */
    public function clear(Participant $participant, Phase $phase, string $receiptId): void
    {
        DB::transaction(function () use ($participant, $phase, $receiptId): void {
            $phase = $this->lock($participant, $phase);
            $load = ParticipantReceiptContext::active()->where('participant_id', $participant->id)->where('receipt_id', $receiptId)->first();
            if ($load === null) {
                return;
            }
            $load->forceFill(['cleared_at' => now()])->save();
            $this->changed($load, $phase, 'cleared');
        });
    }

    private function lock(Participant $participant, Phase $phase): Phase
    {
        Substrate::query()->lockForUpdate()->findOrFail($participant->substrate_id);
        Participant::query()->lockForUpdate()->findOrFail($participant->id);
        $current = Phase::query()->lockForUpdate()->findOrFail($phase->id);
        if ($current->cycle->participant_id !== $participant->id || $current->cycle->participant->substrate_id !== $participant->substrate_id
            || $current->completed_at !== null || $current->cycle->completed_at !== null) {
            throw new DomainException('Receipt context changes require this Participant’s active Phase.');
        }

        return $current;
    }

    private function changed(ParticipantReceiptContext $load, Phase $phase, string $action): void
    {
        $event = new ReceiptContextEvent;
        $event->forceFill(['participant_receipt_context_id' => $load->id, 'phase_id' => $phase->id, 'action' => $action])->save();
        $event->setRelation('phase', $phase);
        $event->setRelation('context', $load);
        app(LedgerService::class)->record($event);
        // Linked classifications consume the same load state. Invalidate through the existing field path.
        $affected = DB::table('proposition_receipt_links')->where('receipt_id', $load->receipt_id)->pluck('proposition_id')->push($load->proposition_id)->unique();
        app(FieldMutationService::class)->touch($phase, $affected->all());
        foreach (Proposition::whereKey($affected)->get() as $proposition) {
            $this->measurements->recalculateIfMeasurable($proposition);
        }
        app(RealtimePublisher::class)->participant($load->participant_id);
        app(RealtimePublisher::class)->proposition($load->proposition_id);
    }
}
