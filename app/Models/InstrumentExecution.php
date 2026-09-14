<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstrumentExecution extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $execution): void {
            if ($execution->isDirty(['phase_id', 'proposition_id', 'kind', 'reference', 'input', 'replayed_from_receipt_id'])
                || ($execution->getRawOriginal('status') !== 'pending' && $execution->isDirty())) {
                throw new \DomainException('Instrument invocation and completed execution history are immutable.');
            }
        });
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function replayedFromReceipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'replayed_from_receipt_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
