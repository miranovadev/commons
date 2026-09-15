<?php

namespace App\Services\Evidence;

use App\Enums\ReceiptCapability;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\Participation\ParticipationContext;

/** Evidence identity is independent of its producer and of participant uptake. */
final class ReceiptEvidenceService
{
    public function describe(Receipt $receipt, ParticipationContext $context): ?array
    {
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
