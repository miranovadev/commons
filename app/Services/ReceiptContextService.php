<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Enums\ReceiptCapability;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
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
                PhaseKind::Bias => app(HomologousBiasService::class)->foreignGateIdsFor($phase, $context)
                    ->merge(app(MediatedIncomingBiasService::class)->foreignGateIdsFor($phase, $context)),
                default => collect(),
            };
            $exposed = $gate === null ? collect() : $this->lineage->endingAt($gate, $context)->concat([$gate])
                ->concat($this->mediation->visibleReadsFor($phase, $context))
                ->concat($this->options->gateOptionsFor($phase, $context)->whereNotIn('id', $foreignOperands))
                ->concat($this->options->writeOptionsFor($phase, $context))
                ->concat($this->options->instrumentOptionsFor($phase, $context))
                ->unique('id')->filter(fn ($node) => $context->activeIds->has($node->id));
            $present = $exposed->pluck('id');
            $available = app(HorizonReceiptService::class)->catalogue($phase, $context);
            // Memory survives spent authority, exhaustion and recovery. Only this
            // Participant's already loaded, same-substrate memories are retained.
            $memories = Receipt::where('type', 'tragedy')
                ->whereHas('horizon', fn ($q) => $q->where('substrate_id', $context->substrate->id))
                ->where(fn ($q) => $q->whereIn('id', ParticipantReceiptContext::active()
                    ->where('participant_id', $phase->cycle->participant_id)->select('receipt_id'))
                    // Optional loading and escape can accompany the same atomic action.
                    ->orWhereHas('consumption', fn ($q) => $q->where('phase_id', $phase->id)))->get()
                ->concat(Receipt::where('type', 'proposition_search')
                    ->whereIn('id', ParticipantReceiptContext::active()
                        ->where('participant_id', $phase->cycle->participant_id)->select('receipt_id'))
                    ->whereHas('evidenceExecution.proposition.phase.cycle.participant',
                        fn ($q) => $q->where('substrate_id', $context->substrate->id))->get());
            $receipts = $available
                ->concat($memories)
                ->concat($context->receipts->where('context_horizon_id', $phase->cycle->context_horizon_id)
                    ->whereIn('id', $context->receiptContexts->where('participant_id', $phase->cycle->participant_id)->whereNull('cleared_at')->pluck('receipt_id')))
                ->concat($exposed->flatMap(fn ($node) => $this->state->receipts($node, $context)))->unique('id');
            (new \Illuminate\Database\Eloquent\Collection($receipts->all()))->loadMissing(['consumption', 'phase', 'evidenceExecution']);
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

            return $receipts->map(function ($receipt) use ($phase, $context, $loads, $present, $replayTargets, $available, $activeIds): array {
                $verified = $this->evidence->describe($receipt, $context);
                $load = $loads->get($receipt->id);
                $memory = $receipt->isSpecial();
                $loaded = $load !== null && $load->cleared_at === null;
                $alreadyPresent = isset($verified['referenced_proposition_id']) && $present->contains($verified['referenced_proposition_id']);
                $status = $loaded ? 'loaded' : ($memory ? 'available' : ($verified === null && $receipt->type !== 'load_file' ? 'unqualified' : ($alreadyPresent ? 'already-in-context' : 'available')));

                return [
                    'id' => $receipt->id, 'proposition_id' => $receipt->type === 'tragedy'
                        ? $receipt->metadata['terminal_proposition_id']
                        : ($receipt->evidenceExecution?->proposition_id ?? $receipt->phase->gate_proposition_id),
                    'context_horizon_id' => $receipt->context_horizon_id,
                    'availability' => $receipt->consumption !== null ? 'consumed' :
                        ($available->contains('id', $receipt->id)
                            ? 'available' : 'historical'),
                    'consumed_at' => $receipt->consumption?->consumed_at?->toISOString(),
                    'attached_proposition_id' => $receipt->consumption?->proposition_id,
                    'type' => $receipt->type, 'reference' => $receipt->reference,
                    ...($receipt->type === 'load_file' ? ['acquisition' => $this->evidence->acquisition($receipt)] : []),
                    ...($receipt->type === 'preparation' && $verified !== null ? ['preparation_history' => $verified['evidence']] : []),
                    ...($receipt->type === 'tragedy' ? ['purpose' => 'homologous_boundary_escape', 'warrant' => app(TragedyEvidenceService::class)->describe($receipt)] : []),
                    'capabilities' => [
                        'readable' => $receipt->supports(ReceiptCapability::Readable),
                        'replayable' => $receipt->supports(ReceiptCapability::Replayable),
                    ],
                    'can_replay' => $receipt->supports(ReceiptCapability::Replayable)
                        && $replayTargets->contains($receipt->evidenceExecution->proposition_id)
                        && app(RelevantInstrumentService::class)->equivalent(
                            $context->propositions->get($receipt->evidenceExecution->proposition_id),
                            $receipt->replayInvocation(), $context,
                        ) === null,
                    'replayed_from_receipt_id' => $receipt->evidenceExecution?->replayed_from_receipt_id,
                    'observation_error' => $receipt->metadata['observation_error'] ?? null,
                    'signature' => $verified['signature'] ?? null,
                    'loaded' => $loaded, 'active' => ! $memory && $loaded && $activeIds->contains($receipt->id),
                    ...($memory ? ['load_mode' => 'memory', 'memory_active' => $loaded,
                        ...($receipt->type === 'tragedy' ? [
                            'consumed_in_current_phase' => $receipt->consumption?->phase_id === $phase->id,
                            ...($loaded ? ['tragedy_memory' => app(TragedyMemoryService::class)->describe($receipt, $phase)] : []),
                        ] : []),
                    ] : []),
                    ...($receipt->type === 'proposition_search' && $loaded
                        ? ['proposition_collection' => $receipt->metadata['proposition_collection']] : []),
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
            if ($option === null || $option['type'] === 'tragedy' || ! $option['capabilities']['readable'] || ! in_array($option['status'], ['available', 'loaded'], true)) {
                throw new DomainException('Read requires qualifying encountered evidence or cognitive memory outside the current context.');
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
            $references = $this->state->evidenceReceipts($gate, $context)
                ->filter(fn ($receipt) => app(ReceiptRegionService::class)->contains($receipt, $phase, $context))->pluck('id');

            return $this->forPhase($phase, $context)->whereIn('id', $references)
                ->filter(fn ($item) => $item['capabilities']['readable'] && $item['signature'] !== null)->values();
        });
    }

    /** Eligible local Read/Gate/Write associations; visibility is never authority. */
    public function loadOptions(Phase $phase, ?ParticipationContext $context = null): Collection
    {
        $context ??= new ParticipationContext($phase);

        return $context->remember('receipt-load-options', function () use ($phase, $context) {
            $parentIds = $context->parents->get($phase->gate_proposition_id, collect())->pluck('source_proposition_id');
            $nodes = collect([$context->propositions->get($phase->gate_proposition_id)])->filter()
                ->concat($context->propositions->only($parentIds->all())->values())
                ->concat($this->mediation->visibleReadsFor($phase, $context))
                ->concat($this->options->gateOptionsFor($phase, $context))
                ->concat($this->options->writeOptionsFor($phase, $context))
                ->concat($this->options->traversalOptionsFor($phase, $context))
                ->unique('id');
            $references = $nodes->flatMap(fn ($node) => $this->state->evidenceReceipts($node, $context))
                ->unique('id')->filter(fn ($receipt) => app(ReceiptRegionService::class)->contains($receipt, $phase, $context))->keyBy('id');

            return $this->forPhase($phase, $context)->filter(function ($item) use ($references): bool {
                if ($item['type'] === 'tragedy') {
                    return $item['loaded'] || $item['availability'] === 'available' || $item['consumed_in_current_phase'];
                }

                return $references->has($item['id']) && $item['capabilities']['readable']
                    && ($item['signature'] !== null || $this->evidence->failure($references[$item['id']]) !== null);
            })->values();
        });
    }

    /** Personal same-substrate memory may be cleared even after leaving its position. */
    public function unloadOptions(Phase $phase): Collection
    {
        return ParticipantReceiptContext::active()->where('participant_id', $phase->cycle->participant_id)
            ->whereHas('receipt.horizon', fn ($q) => $q->where('substrate_id', $phase->cycle->participant->substrate_id))
            ->orderBy('receipt_id')->pluck('receipt_id');
    }

    /** Called after immutable ordinary observation persistence, including Preparation failures. */
    public function loadCreated(Receipt $receipt): ?ParticipantReceiptContext
    {
        if (DB::transactionLevel() === 0 || ! $receipt->observationComplete()) {
            throw new DomainException('Creator loading requires a recorded observation in its transaction.');
        }
        if (! $receipt->supports(ReceiptCapability::Readable)) {
            return null;
        }
        $phase = $receipt->evidenceExecution->phase;
        $participant = $phase->cycle->participant;
        Participant::query()->lockForUpdate()->findOrFail($participant->id);
        Phase::query()->lockForUpdate()->findOrFail($phase->id);
        $signature = $receipt->type === 'citation'
            ? ($this->evidence->describe($receipt, new ParticipationContext($phase))['signature'] ?? null)
            : ($this->evidence->observation($receipt)['signature'] ?? null);

        return $this->persistLoad($participant, $phase, $receipt->id, $receipt->evidenceExecution->proposition_id, $signature);
    }

    private function persistLoad(Participant $participant, Phase $phase, string $id, string $propositionId, ?string $signature): ParticipantReceiptContext
    {
        $load = ParticipantReceiptContext::where('participant_id', $participant->id)->where('receipt_id', $id)->first();
        if ($load !== null && $load->cleared_at === null) {
            return $load;
        }
        $load ??= new ParticipantReceiptContext;
        $load->forceFill(['participant_id' => $participant->id, 'receipt_id' => $id,
            'proposition_id' => $propositionId, 'evidence_signature' => $signature,
            'loaded_at' => now(), 'cleared_at' => null])->save();
        $this->changed($load, $phase, 'loaded');

        return $load;
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
        $eligible = $this->loadOptions($phase)->keyBy('id');
        if (array_diff($loadIds, $eligible->keys()->all()) !== []
            || array_diff($unloadIds, $this->unloadOptions($phase)->all()) !== []) {
            throw new DomainException('Receipt options require eligible local evidence/scoped memory or a Receipt loaded by this Participant.');
        }
        foreach (array_unique($loadIds) as $id) {
            $option = $eligible[$id];
            $this->persistLoad($participant, $phase, $id, $option['proposition_id'], $option['signature']);
        }
        foreach (array_unique($unloadIds) as $id) {
            $this->clear($participant, $phase, $id);
        }
    }

    /** Internal desired-state primitive; public options are Participant-validated above. */
    public function clear(Participant $participant, Phase $phase, string $receiptId): void
    {
        DB::transaction(function () use ($participant, $phase, $receiptId): void {
            $phase = $this->lock($participant, $phase);
            $load = ParticipantReceiptContext::active()->where('participant_id', $participant->id)->where('receipt_id', $receiptId)
                ->whereHas('receipt.horizon', fn ($q) => $q->where('substrate_id', $participant->substrate_id))->first();
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
        if ($load->receipt->isSpecial() || $load->receipt->isPreparationFailure()) {
            // Cognition only: no field invalidation, recalculation or evidence signature.
            app(RealtimePublisher::class)->participant($load->participant_id);

            return;
        }
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
