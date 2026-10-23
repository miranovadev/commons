<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EvidenceExecution extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = ['type' => 'instrument', 'status' => 'pending'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'metadata' => 'array',
            'presentation' => 'array',
            'presented_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $execution): void {
            $cycleId = $execution->phase()->firstOrFail()->cycle_id;
            if ($execution->cycle_id !== null && $execution->cycle_id !== $cycleId) {
                throw new \DomainException('Execution Cycle must match its creating Phase.');
            }
            $execution->cycle_id = $cycleId;
            if ($execution->status !== 'pending') {
                throw new \DomainException('An evidence execution starts pending.');
            }
        });
        static::saving(function (self $execution): void {
            if (! in_array($execution->type, ['instrument', 'prepare'], true)
                || ($execution->type === 'prepare' && ($execution->kind !== null || $execution->reference !== null || $execution->replayed_from_receipt_id !== null))
                || ($execution->type === 'instrument' && ($execution->kind === null || $execution->reference === null || $execution->outcome !== null))
                || ($execution->type === 'prepare' && (($execution->status === 'completed') !== ($execution->outcome !== null)))
                || (($execution->outcome === 'contributed') !== ($execution->resulting_proposition_id !== null))
                || (($execution->status === 'completed') !== ($execution->completed_at !== null))
                || (($execution->status === 'failed') !== ($execution->failed_at !== null))
                || (($execution->presentation !== null) !== ($execution->presented_at !== null))
                || ($execution->type === 'instrument' && $execution->presentation !== null)
                || ($execution->type === 'prepare' && in_array($execution->status, ['presented', 'completed'], true) && $execution->presentation === null)
                || ($execution->status === 'pending' && $execution->presentation !== null)) {
                throw new \DomainException('Execution type, invocation and outcome must agree.');
            }
        });
        static::updating(function (self $execution): void {
            $before = $execution->getRawOriginal('status');
            $allowed = $execution->type === 'instrument'
                ? ['pending' => ['completed', 'failed']]
                : ['pending' => ['presented', 'failed'], 'presented' => ['completed', 'failed']];
            if ($execution->isDirty(['cycle_id', 'type', 'phase_id', 'proposition_id', 'kind', 'reference', 'input', 'replayed_from_receipt_id', 'started_at'])
                || ! in_array($execution->status, $allowed[$before] ?? [], true)
                || ($before === 'presented' && $execution->isDirty(['presentation', 'presented_at']))) {
                throw new \DomainException('Evidence invocation, presentation and terminal execution history are immutable.');
            }
        });
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function resultingProposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class, 'resulting_proposition_id');
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
