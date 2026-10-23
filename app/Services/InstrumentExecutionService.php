<?php

namespace App\Services;

use App\Domain\Evidence\PropositionCollection;
use App\Domain\Evidence\SearchObservation;
use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\ReceiptCapability;
use App\Models\Attestation;
use App\Models\InstrumentExecution;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\Evidence\ReinforcementSignatureService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class InstrumentExecutionService
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
    ): InstrumentExecution {
        $substrateId = $phase->cycle->participant->substrate_id;
        if ($proposition->phase->cycle->participant->substrate_id !== $substrateId) {
            throw new DomainException('An instrument cannot cross substrate boundaries.');
        }
        if ($replayedFrom !== null && ($replayedFrom->instrumentExecution->proposition_id !== $proposition->id
            || ! $replayedFrom->supports(ReceiptCapability::Replayable)
            || $replayedFrom->replayInvocation() !== $input)) {
            throw new DomainException('Replay must preserve its originating invocation and Proposition.');
        }
        $execution = new InstrumentExecution;
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
        InstrumentExecution $execution,
        Model $receiptable,
        ?string $signature = null,
        array $metadata = [],
        ?VerifiedHyperlink $verification = null,
        ?SearchObservation $observation = null,
        ?PropositionCollection $propositionCollection = null,
    ): Receipt {
        return DB::transaction(function () use (
            $execution,
            $receiptable,
            $signature,
            $metadata,
            $verification,
            $observation,
            $propositionCollection,
        ): Receipt {
            // Resolve ownership with a read; never lock the execution before its root.
            $substrateId = InstrumentExecution::query()->findOrFail($execution->getKey())->phase->cycle->participant->substrate_id;
            Substrate::query()->lockForUpdate()->findOrFail($substrateId);
            $locked = InstrumentExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->getKey());

            if ($locked->status !== 'pending') {
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
            $metadata['reinforcement_status'] = 'not-loaded';
            $receipt = new Receipt;
            $receipt->forceFill([
                'instrument_execution_id' => $locked->getKey(),
                'type' => $locked->kind,
                'capabilities' => ReceiptCapability::forInstrument($locked->kind),
                'reference' => $locked->reference,
                'signature' => $resolution['signature'],
                'metadata' => $metadata,
            ]);
            $receipt->receiptable()->associate($locked->proposition);
            $receipt->save();

            $this->ledger->record($receipt);

            $locked->status = 'completed';
            $locked->metadata = $metadata;
            $locked->completed_at = now();
            $locked->save();

            $receipt->setRelation('instrumentExecution', $locked);
            app(ReceiptContextService::class)->loadCreated($receipt);

            $proposition = $locked->proposition()->first();
            if ($proposition !== null && $receipt->isEvidence()) {
                $this->measurements->recalculateIfMeasurable($proposition);
            }

            return $receipt;
        });
    }

    public function fail(
        InstrumentExecution $execution,
        string $reason,
    ): void {
        // This transition never enters ledger/projection code; the execution is its whole invariant.
        DB::transaction(function () use ($execution, $reason): void {
            $locked = InstrumentExecution::query()->lockForUpdate()->findOrFail($execution->id);
            if ($locked->status !== 'pending') {
                throw new DomainException('Only a pending instrument execution can fail.');
            }
            $locked->forceFill(['status' => 'failed', 'failure_reason' => $reason, 'failed_at' => now()])->save();
        });
    }
}
