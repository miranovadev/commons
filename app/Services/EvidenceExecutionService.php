<?php

namespace App\Services;

use App\Domain\Evidence\DownloadObservation;
use App\Domain\Evidence\PreparationInput;
use App\Domain\Evidence\PreparationPresentation;
use App\Domain\Evidence\PropositionCollection;
use App\Domain\Evidence\SearchObservation;
use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\ReceiptCapability;
use App\Models\Attestation;
use App\Models\EvidenceExecution;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\Evidence\ReceiptSummary;
use App\Services\Evidence\ReinforcementSignatureService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class EvidenceExecutionService
{
    public function __construct(
        private LedgerService $ledger,
        private PropositionMeasurementService $measurements,
        private ReinforcementSignatureService $signatures,
    ) {}

    /** @param array<string, mixed> $input */
    public function start(
        Phase $phase,
        Proposition $proposition,
        string $kind,
        string $reference,
        array $input = [],
        ?Receipt $replayedFrom = null,
    ): EvidenceExecution {
        $substrateId = $phase->cycle->participant->substrate_id;
        if ($proposition->phase->cycle->participant->substrate_id !== $substrateId) {
            throw new DomainException('An instrument cannot cross substrate boundaries.');
        }
        if ($replayedFrom !== null && ($replayedFrom->evidenceExecution->proposition_id !== $proposition->id
            || ! $replayedFrom->supports(ReceiptCapability::Replayable)
            || $replayedFrom->replayInvocation() !== $input)) {
            throw new DomainException('Replay must preserve its originating invocation and Proposition.');
        }
        $execution = new EvidenceExecution;
        $execution->forceFill([
            'phase_id' => $phase->getKey(),
            'proposition_id' => $proposition->getKey(),
            'kind' => $kind,
            'reference' => $reference,
            'status' => 'pending',
            'input' => $input,
            'replayed_from_receipt_id' => $replayedFrom?->id,
            'started_at' => now(),
        ]);
        $execution->save();

        return $execution;
    }

    /** @param array<string, mixed> $metadata */
    public function complete(
        EvidenceExecution $execution,
        Model $receiptable,
        ?string $signature = null,
        array $metadata = [],
        ?VerifiedHyperlink $verification = null,
        ?SearchObservation $observation = null,
        ?PropositionCollection $propositionCollection = null,
        ?DownloadObservation $download = null,
    ): Receipt {
        return DB::transaction(function () use (
            $execution,
            $receiptable,
            $signature,
            $metadata,
            $verification,
            $observation,
            $propositionCollection,
            $download,
        ): Receipt {
            // Resolve ownership with a read; never lock the execution before its root.
            $substrateId = EvidenceExecution::query()->findOrFail($execution->getKey())->phase->cycle->participant->substrate_id;
            Substrate::query()->lockForUpdate()->findOrFail($substrateId);
            $locked = EvidenceExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->getKey());

            if ($locked->type === 'instrument' && $locked->observationRecorded()
                && ($receiptable instanceof Proposition ? $receiptable->id : ($receiptable instanceof Attestation ? $receiptable->proposition_id : null)) === $locked->proposition_id) {
                return $locked->receipt()->firstOrFail();
            }

            if ($locked->type !== 'instrument' || $locked->status !== 'pending') {
                throw new DomainException(
                    'Only a pending instrument execution can complete.'
                );
            }

            $targetId = $receiptable instanceof Proposition ? $receiptable->id
                : ($receiptable instanceof Attestation ? $receiptable->proposition_id : null);
            if ($targetId !== $locked->proposition_id) {
                throw new DomainException('The Receipt subject must belong to the verified Proposition.');
            }
            // Caller-supplied strings/metadata are evidence history, never verification authority.
            $resolution = $locked->kind === 'hyperlink' && $verification !== null
                ? $this->signatures->resolve($verification)
                : ['signature' => null, 'status' => 'non-reinforcing'];
            $metadata = [...$metadata, ...($signature !== null ? ['supplied_signature' => $signature] : []), ...($verification?->metadata() ?? ['content_verified' => false]),
                'reinforcement_version' => $resolution['status']];
            if ($locked->kind === 'load_file') {
                if ($download === null || $download->sourceUrl !== ($locked->input['reference'] ?? null)
                    || ($locked->input['method'] ?? null) !== 'download') {
                    throw new DomainException('The Download observation must match its stored invocation.');
                }
                $resolution = ['signature' => $download->successful() ? $download->identity() : null];
                $metadata = [...$metadata, ...$download->metadata, 'observation_identity' => $download->identity(),
                    'reinforcement_version' => 'non-loadable'];
            } elseif ($download !== null) {
                throw new DomainException('Only Load File may persist a Download observation.');
            }
            if ($locked->kind === 'proposition_search') {
                if ($propositionCollection === null
                    || $propositionCollection->headPropositionId !== $locked->proposition_id
                    || $propositionCollection->query !== ($locked->input['query'] ?? null)
                    || $propositionCollection->limit !== ($locked->input['limit'] ?? null)) {
                    throw new DomainException('The Proposition Search collection must match its stored invocation and head.');
                }
                $resolution = ['signature' => $propositionCollection->identity(), 'status' => 'observed-proposition-search'];
                $metadata = [...$metadata,
                    'proposition_collection' => $propositionCollection->payload(),
                    'observation_identity' => $propositionCollection->identity(),
                    'content_verified' => true,
                    'reinforcement_version' => $resolution['status']];
            } elseif ($propositionCollection !== null) {
                throw new DomainException('Only Proposition Search may persist a PropositionCollection.');
            }
            if ($locked->kind === 'web_search' && $observation !== null) {
                if ($observation->query !== ($locked->input['query'] ?? null)) {
                    throw new DomainException('The Search observation must match its stored invocation.');
                }
                $resolution = ['signature' => $observation->signature(), 'status' => 'observed-search'];
                $metadata = [...$metadata, 'observation' => $observation->evidence(),
                    'content_verified' => true, 'reinforcement_version' => $resolution['status']];
            }

            return $this->receipt($locked, $locked->kind, $locked->reference, $resolution['signature'], $metadata);
        });
    }

    /** Called after Preparation's ownership/selection checks under the shared root lock. */
    public function startPreparation(Phase $phase, PreparationInput $input): EvidenceExecution
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Preparation start requires its authoritative transaction.');
        }
        $execution = new EvidenceExecution;
        $execution->forceFill(['type' => 'prepare', 'phase_id' => $phase->id,
            'proposition_id' => $phase->gate_proposition_id, 'status' => 'pending', 'attempt_admitted_at' => now(),
            'input' => $input->payload(), 'started_at' => now()])->save();

        return $execution;
    }

    /** Validated delivery acknowledgements contain identifiers, never Artifact bodies. */
    public function present(EvidenceExecution $execution, PreparationPresentation $presentation): void
    {
        if (DB::transactionLevel() === 0 || $execution->type !== 'prepare' || $execution->status !== 'pending') {
            throw new DomainException('Only a locked pending Preparation can be presented.');
        }
        $execution->forceFill(['status' => 'presented', 'presentation' => $presentation->deliveries, 'presented_at' => now()])->save();
    }

    /** The caller creates the contribution and advances it in this same transaction. */
    public function completePreparation(EvidenceExecution $execution, ?Proposition $result, ?string $reason, ?string $resolutionHash = null): Receipt
    {
        if (DB::transactionLevel() === 0 || $execution->type !== 'prepare' || $execution->status !== 'presented'
            || $execution->presentation === null || (($result !== null) === ($reason !== null))
            || ($result !== null && ($result->phase_id !== $execution->phase_id || ! $result->wasRecentlyCreated))) {
            throw new DomainException('Preparation resolution requires presented material and exactly one real outcome.');
        }
        $execution->outcome = $result === null ? 'deferred' : 'contributed';
        $execution->resulting_proposition_id = $result?->id;
        $metadata = [
            'resolution_hash' => $resolutionHash,
            'originating_gate_id' => $execution->proposition_id,
            'participant_id' => $execution->phase->cycle->participant_id,
            'purpose' => $execution->input['purpose'], 'selections' => $execution->input['artifacts'],
            'deliveries' => $execution->presentation,
            'presented_at' => $execution->presented_at->toISOString(),
            'outcome' => ['type' => $execution->outcome, ...($result === null
                ? ['reason' => $reason] : ['proposition_id' => $result->id])],
            'content_verified' => true, 'reinforcement_version' => 'observed-preparation',
        ];

        return $this->receipt($execution, 'preparation', $execution->id,
            hash('sha256', 'preparation:v1:'.$execution->id), $metadata);
    }

    /** One immutable ordinary Receipt construction and creator-uptake contract. */
    private function receipt(EvidenceExecution $execution, string $type, string $reference, ?string $signature, array $metadata): Receipt
    {
        $metadata['reinforcement_status'] = 'not-loaded';
        $receipt = new Receipt;
        $receipt->forceFill([
            'evidence_execution_id' => $execution->id, 'type' => $type,
            'capabilities' => ReceiptCapability::forReceipt($type),
            'reference' => $reference, 'signature' => $signature, 'metadata' => $metadata,
        ]);
        $receipt->receiptable()->associate($execution->proposition);
        $receipt->save();
        $this->ledger->record($receipt);
        if ($execution->status !== 'failed') {
            $execution->forceFill(['status' => 'completed', 'metadata' => $metadata, 'completed_at' => now()])->save();
        }
        $receipt->setRelation('evidenceExecution', $execution);
        app(ReceiptContextService::class)->loadCreated($receipt);
        if (! $receipt->isFailure()) {
            $this->measurements->recalculateIfMeasurable($execution->proposition);
        }

        return $receipt;
    }

    /** First terminal writer wins. Historical failures are never backfilled by retries. */
    public function fail(EvidenceExecution $execution, string $reason = '', array $diagnostic = [], bool $variantObservation = false): ?Receipt
    {
        return DB::transaction(function () use ($execution, $reason, $diagnostic, $variantObservation): ?Receipt {
            $participant = $execution->phase->cycle->participant;
            Substrate::lockForUpdate()->findOrFail($participant->substrate_id);
            $locked = EvidenceExecution::lockForUpdate()->findOrFail($execution->id);
            if (! in_array($locked->status, ['pending', 'presented'], true)) {
                return $locked->status === 'failed' ? $locked->receipt()->first() : null;
            }

            $at = now();
            $kind = $locked->kind ?? 'preparation';
            $reason = ReceiptSummary::text(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $reason) ?? ''));
            $reason = $reason !== '' ? $reason : 'No diagnostic details were recorded.';
            $failure = array_filter(array_map(ReceiptSummary::text(...), array_intersect_key($diagnostic,
                array_flip(['stage', 'classification', 'method', 'version', 'source_type', 'read_type', 'artifact_id']))));
            $failure += ['stage' => 'execution', 'classification' => 'unclassified'];
            // URLs can contain credentials in the path, query or fragment. Retain
            // only the host. The private execution still owns the exact invocation.
            $input = $locked->input ?? [];
            $attempted = match ($kind) {
                'citation' => preg_match('/'.Evidence\InstrumentInvocation::CITATION_PATTERN.'/D', $locked->reference) ? ['subject' => $locked->reference] : [],
                'hyperlink', 'load_file' => array_filter(['host' => parse_url($locked->reference, PHP_URL_HOST)]),
                'web_search', 'proposition_search' => array_intersect_key($input, ['limit' => true]) + ['query_length' => mb_strlen($input['query'] ?? '')],
                default => [],
            };
            $metadata = [
                'execution_id' => $locked->id, 'originating_gate_id' => $locked->proposition_id, 'participant_id' => $participant->id,
                'kind' => $kind, 'failed_at' => $at->toISOString(), 'failure' => $failure, 'failure_reason' => $reason,
                'attempted_input' => $attempted, 'outcome' => ['type' => 'failed'], 'variant_observation' => $variantObservation,
                'content_verified' => false, 'reinforcement_version' => 'acquisition-failure',
            ];
            if ($locked->type === 'prepare') {
                $metadata += ['purpose' => ReceiptSummary::text($input['purpose'] ?? ''), 'selections' => $input['artifacts'] ?? [],
                    'deliveries' => $locked->presentation ?? [], 'presented_at' => $locked->presented_at?->toISOString()];
            }
            $locked->forceFill(['status' => 'failed', 'outcome' => 'failed', 'failure_reason' => $reason,
                'failed_at' => $at, 'metadata' => $metadata])->save();
            $receipt = $this->receipt($locked, 'failure', $locked->id, null, $metadata);
            DB::afterCommit(fn () => app(PotentialProjectionService::class)->refresh($participant->substrate_id));

            return $receipt;
        }, attempts: 3);
    }
}
