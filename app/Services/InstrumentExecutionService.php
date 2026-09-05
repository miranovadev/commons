<?php

namespace App\Services;

use App\Models\InstrumentExecution;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class InstrumentExecutionService
{
    /**
     * @param  array<string, mixed>  $input
     */
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

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function complete(
        InstrumentExecution $execution,
        Model $receiptable,
        string $signature,
        array $metadata = [],
    ): Receipt {
        return DB::transaction(function () use (
            $execution,
            $receiptable,
            $signature,
            $metadata,
        ): Receipt {
            $locked = InstrumentExecution::query()
                ->lockForUpdate()
                ->findOrFail($execution->getKey());

            if ($locked->status !== 'pending') {
                throw new DomainException(
                    'Only a pending instrument execution can complete.'
                );
            }

            $receipt = new Receipt;
            $receipt->forceFill([
                'instrument_execution_id' => $locked->getKey(),
                'type' => $locked->kind,
                'reference' => $locked->reference,
                'signature' => $signature,
                'metadata' => $metadata,
            ]);
            $receipt->receiptable()->associate($receiptable);
            $receipt->save();

            $locked->status = 'completed';
            $locked->metadata = $metadata;
            $locked->completed_at = now();
            $locked->save();

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
