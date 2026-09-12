<?php

namespace App\Services;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Models\Attestation;
use App\Models\InstrumentExecution;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Services\Evidence\ReinforcementRecorder;
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
        private ReinforcementRecorder $reinforcement,
    ) {}

    /** @param array<string, mixed> $input */
    public function start(
        Phase $phase,
        Proposition $proposition,
        string $kind,
        string $reference,
        array $input = [],
    ): InstrumentExecution {
        $execution = new InstrumentExecution;
        $execution->forceFill([
            'phase_id' => $phase->getKey(),
            'proposition_id' => $proposition->getKey(),
            'kind' => $kind,
            'reference' => $reference,
            'status' => 'pending',
            'input' => $input,
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
    ): Receipt {
        return DB::transaction(function () use (
            $execution,
            $receiptable,
            $signature,
            $metadata,
            $verification,
        ): Receipt {
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
            Substrate::whereKey($locked->phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
            // Caller-supplied strings/metadata are evidence history, never verification authority.
            $resolution = $locked->kind === 'hyperlink' && $verification !== null
                ? $this->signatures->resolve($verification)
                : ['signature' => null, 'status' => 'non-reinforcing'];
            $metadata = [...$metadata, ...($signature !== null ? ['supplied_signature' => $signature] : []), ...($verification?->metadata() ?? ['content_verified' => false]),
                'reinforcement_version' => $resolution['status']];
            $receipt = new Receipt;
            $receipt->forceFill([
                'instrument_execution_id' => $locked->getKey(),
                'type' => $locked->kind,
                'reference' => $locked->reference,
                'signature' => $resolution['signature'],
                'metadata' => $metadata,
            ]);
            $receipt->receiptable()->associate($receiptable);
            $receipt->save();

            $this->ledger->record($receipt);

            $locked->status = 'completed';
            $locked->metadata = $metadata;
            $locked->completed_at = now();
            $locked->save();

            $receipt->setRelation('instrumentExecution', $locked);
            $metadata['reinforcement_status'] = $this->reinforcement->record($receipt);
            $receipt->metadata = $metadata;
            $receipt->save();
            $locked->metadata = $metadata;
            $locked->save();

            $proposition = $locked->proposition()->first();
            if ($proposition !== null) {
                // Evidence affects local comparisons and all their propagated projections.
                PropositionMeasurement::whereHas('proposition.phase.cycle.participant', fn ($q) => $q
                    ->where('substrate_id', $locked->phase->cycle->participant->substrate_id))
                    ->update(['calculator_version' => null]);
                $this->measurements->recalculateIfMeasurable($proposition);
            }

            return $receipt;
        });
    }

    public function fail(
        InstrumentExecution $execution,
        string $reason,
    ): void {
        DB::transaction(function () use ($execution, $reason): void {
            $locked = InstrumentExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->getKey());

            if ($locked->status !== 'pending') {
                throw new DomainException(
                    'Only a pending instrument execution can fail.'
                );
            }

            $locked->status = 'failed';
            $locked->failure_reason = $reason;
            $locked->failed_at = now();
            $locked->save();
        });
    }
}
