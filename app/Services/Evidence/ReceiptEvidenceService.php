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
                ->whereHas('instrumentExecution', fn ($q) => $q->where('status', 'completed')
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
        if ($receipt->type === 'tragedy') {
            return $receipt->context_horizon_id === $context->phase->cycle->context_horizon_id
                ? ['signature' => hash('sha256', 'tragedy:'.$receipt->id),
                    'evidence' => app(\App\Services\TragedyEvidenceService::class)->describe($receipt)] : null;
        }
        $execution = $receipt->instrumentExecution;
        if (! $receipt->supports(ReceiptCapability::Readable) || $execution?->status !== 'completed' || ! $context->propositions->has($execution->proposition_id)) {
            return null;
        }
        if ($receipt->type === 'hyperlink' && $receipt->signature !== null
            && ($receipt->metadata['content_verified'] ?? false) === true
            && ($receipt->metadata['reinforcement_version'] ?? null) === 'accepted-version') {
            return [
                'signature' => $receipt->signature,
                'evidence' => array_intersect_key($receipt->metadata, array_flip([
                    'resource', 'fingerprint', 'observed_at', 'last_modified', 'content_excerpt', 'excerpt_truncated',
                ])),
            ];
        }
        if ($receipt->type === 'web_search' && $receipt->signature !== null
            && ($receipt->metadata['content_verified'] ?? false) === true
            && ($receipt->metadata['reinforcement_version'] ?? null) === 'observed-search'
            && is_array($receipt->metadata['observation'] ?? null)) {
            return ['signature' => $receipt->signature, 'evidence' => $receipt->metadata['observation']];
        }
        if ($receipt->type !== 'citation') {
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
}
