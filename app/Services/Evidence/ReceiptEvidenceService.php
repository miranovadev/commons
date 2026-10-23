<?php

namespace App\Services\Evidence;

use App\Enums\ReceiptCapability;
use App\Models\Attestation;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Evidence identity is independent of its producer and of participant uptake. */
final class ReceiptEvidenceService
{
    /** Persistent personal citation knowledge survives leaving its producing lineage. */
    public function loadedCitationTargets(ParticipationContext $context): Collection
    {
        return $context->remember('loaded-citation-targets', function () use ($context): Collection {
            $references = Receipt::where('type', 'citation')->whereIn('id', ParticipantReceiptContext::active()
                ->where('participant_id', $context->phase->cycle->participant_id)->select('receipt_id'))
                ->whereHas('evidenceExecution', fn ($q) => $q->where('status', 'completed')
                    ->whereHas('proposition.phase.cycle.participant', fn ($q) => $q->where('substrate_id', $context->substrate->id)))
                ->get(['id', 'reference', 'type', 'capabilities'])
                ->filter(fn ($receipt) => $receipt->supports(ReceiptCapability::Readable))->pluck('reference')->unique();
            if ($references->isEmpty()) {
                return collect();
            }
            $owned = fn ($q) => $q->where('substrate_id', $context->substrate->id);

            return Proposition::whereIn('id', $references)->whereHas('phase.cycle.participant', $owned)->pluck('id')
                ->merge(Attestation::whereIn('id', $references)->whereHas('phase.cycle.participant', $owned)->pluck('proposition_id'))->unique();
        });
    }

    public function describe(Receipt $receipt, ParticipationContext $context): ?array
    {
        if ($receipt->isSpecial()) {
            return null; // Tragedy retains its separate authority contract.
        }
        $execution = $receipt->evidenceExecution;
        if (! $receipt->supports(ReceiptCapability::Readable) || $execution?->status !== 'completed'
            || ($receipt->context_horizon_id !== $context->phase->cycle->context_horizon_id
                && ! $context->propositions->has($execution->proposition_id))) {
            return null;
        }
        if (($observation = $this->observation($receipt)) !== null) {
            return $observation;
        }

        return $this->citation($receipt, $context);
    }

    /** Canonical citation content for cognition, without admitting operational evidence. */
    public function citation(Receipt $receipt, ParticipationContext $context): ?array
    {
        if ($receipt->type !== 'citation' || ! $receipt->supports(ReceiptCapability::Readable) || ! $receipt->observationComplete()) {
            return null;
        }
        // Resolve the actual same-substrate object, never caller-supplied metadata.
        [$attestation, $target] = $context->remember('citation-target:'.$receipt->reference, function () use ($receipt, $context): array {
            $attestation = $context->attestations->get($receipt->reference);
            $target = $context->propositions->get($receipt->reference)
                ?? ($attestation === null ? null : $context->propositions->get($attestation->proposition_id));
            if ($target === null) {
                // A citation may intentionally point outside the active causal working set.
                // Resolve just its subject, never hydrate that subject's entire branch/evidence.
                $attestation ??= Attestation::whereKey($receipt->reference)
                    ->whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $context->substrate->id))->first();
                $target = Proposition::whereKey($attestation?->proposition_id ?? $receipt->reference)
                    ->whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $context->substrate->id))->first();
            }

            return [$attestation, $target];
        });

        return $this->citationDescription($receipt, $attestation, $target);
    }

    /** Batch cognitive subjects only; never hydrate an operational working graph. */
    public function cognitiveCitations(Collection $receipts, string $substrateId): Collection
    {
        $receipts = $receipts->filter(fn ($receipt) => $receipt->type === 'citation'
            && $receipt->supports(ReceiptCapability::Readable) && $receipt->observationComplete());
        if ($receipts->isEmpty()) {
            return collect();
        }
        $references = $receipts->pluck('reference')->unique();
        $owned = fn ($q) => $q->where('substrate_id', $substrateId);
        $attestations = Attestation::whereKey($references)->whereHas('phase.cycle.participant', $owned)->get()->keyBy('id');
        $targets = Proposition::whereKey($references->merge($attestations->pluck('proposition_id'))->unique())
            ->whereHas('phase.cycle.participant', $owned)->get()->keyBy('id');

        return $receipts->mapWithKeys(function ($receipt) use ($attestations, $targets) {
            $attestation = $attestations->get($receipt->reference);
            $target = $targets->get($receipt->reference) ?? $targets->get($attestation?->proposition_id);

            return [$receipt->id => $this->citationDescription($receipt, $attestation, $target)];
        });
    }

    private function citationDescription(Receipt $receipt, ?Attestation $attestation, ?Proposition $target): ?array
    {
        if ($target === null) {
            return null;
        }

        return [
            'signature' => hash('sha256', 'citation:v1:'.($attestation?->getMorphClass() ?? $target->getMorphClass()).':'.$receipt->reference),
            'referenced_proposition_id' => $target->id,
            'evidence' => [
                'proposition_id' => $target->id, 'statement' => $target->statement,
                ...($attestation === null ? [] : ['attestation_id' => $attestation->id,
                    'direction' => $attestation->direction, 'rationale' => $attestation->rationale]),
            ],
        ];
    }

    /** Immutable observations, independent of operational activation. */
    public function observation(Receipt $receipt): ?array
    {
        if (! $receipt->supports(ReceiptCapability::Readable) || ! $receipt->observationComplete()) {
            return null;
        }
        if ($receipt->type === 'preparation' && $receipt->evidenceExecution->type === 'prepare'
            && $receipt->signature !== null && ($receipt->metadata['content_verified'] ?? false) === true
            && ($receipt->metadata['reinforcement_version'] ?? null) === 'observed-preparation') {
            // Historical delivery/outcome metadata grants no Artifact or Proposition expansion.
            return ['signature' => $receipt->signature, 'evidence' => array_intersect_key($receipt->metadata,
                array_flip(['originating_gate_id', 'participant_id', 'purpose', 'selections', 'deliveries', 'presented_at', 'outcome']))];
        }
        if ($receipt->type === 'proposition_search' && $receipt->signature !== null
            && ($receipt->metadata['content_verified'] ?? false) === true
            && ($receipt->metadata['reinforcement_version'] ?? null) === 'observed-proposition-search'
            && is_array($receipt->metadata['proposition_collection']['results'] ?? null)) {
            // An empty collection is a completed observation, not a failed search.
            return ['signature' => $receipt->signature, 'evidence' => $receipt->metadata['proposition_collection']];
        }
        if ($receipt->type === 'hyperlink' && $receipt->signature !== null
            && ($receipt->metadata['content_verified'] ?? false) === true
            && ($receipt->metadata['reinforcement_version'] ?? null) === 'accepted-version') {
            $content = array_intersect_key($receipt->metadata, array_flip([
                'resource', 'fingerprint', 'observed_at', 'last_modified', 'content_excerpt', 'excerpt_truncated',
                'title', 'title_source', 'description_source',
            ]));
            // Keep the existing small presentation slot; do not duplicate the description
            // or reveal storage keys. Historical Receipts keep their original excerpt.
            if (array_key_exists('description', $receipt->metadata)) {
                $content['content_excerpt'] = $receipt->metadata['description'];
                $content['excerpt_truncated'] = $receipt->metadata['description_truncated'] ?? false;
            }

            return [
                'signature' => $receipt->signature,
                'evidence' => $content,
            ];
        }
        if ($receipt->type === 'web_search' && $receipt->signature !== null
            && ($receipt->metadata['content_verified'] ?? false) === true
            && ($receipt->metadata['reinforcement_version'] ?? null) === 'observed-search'
            && is_array($receipt->metadata['observation'] ?? null)) {
            return ['signature' => $receipt->signature, 'evidence' => $receipt->metadata['observation']];
        }

        return null;
    }

    /** A recorded failure is information, never a qualifying evidence signature. */
    public function failure(Receipt $receipt): ?array
    {
        $metadata = $receipt->metadata ?? [];
        if (! $receipt->observationComplete() || (! isset($metadata['observation_error']) && ! isset($metadata['verification_error']))) {
            return null;
        }

        return ['content_verified' => false, ...array_intersect_key($metadata, array_flip([
            'validation', 'observation_error', 'verification_error',
        ]))];
    }
}
