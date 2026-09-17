<?php

namespace App\Models;

use App\Enums\ReceiptCapability;
use App\Models\Concerns\HasLedgerEntry;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Receipt extends Model
{
    use HasLedgerEntry, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'capabilities' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            $horizonId = $receipt->instrumentExecution?->phase->cycle->context_horizon_id;
            if ($receipt->context_horizon_id !== null && $receipt->context_horizon_id !== $horizonId) {
                throw new DomainException('A Receipt belongs to its creating action’s Horizon.');
            }
            $receipt->context_horizon_id = $horizonId;
        });
        static::updating(function (self $receipt): void {
            if ($receipt->isDirty()) {
                throw new DomainException('Receipts are immutable observations. Execute an Instrument to create a new Receipt.');
            }
        });
    }

    public function capabilityValues(): array
    {
        // Historical Cite/Hyperlink Receipts remain readable. Replay additionally
        // requires a structured originating invocation, never parsed evidence text.
        return $this->capabilities ?? ReceiptCapability::forInstrument($this->type);
    }

    public function supports(ReceiptCapability $capability): bool
    {
        return in_array($capability->value, $this->capabilityValues(), true)
            && ($capability !== ReceiptCapability::Replayable || $this->replayInvocation() !== null);
    }

    public function replayInvocation(): ?array
    {
        $execution = $this->instrumentExecution;
        $input = $execution?->input;
        if ($execution?->status !== 'completed' || ! is_array($input)
            || ($input['kind'] ?? null) !== $this->type) {
            return null;
        }
        if ($this->type === 'web_search' && is_string($input['query'] ?? null)) {
            return ['kind' => 'web_search', 'query' => $input['query']];
        }
        if ($this->type === 'hyperlink' && is_string($input['reference'] ?? null)) {
            return ['kind' => 'hyperlink', 'reference' => trim($input['reference'])];
        }

        return null;
    }

    public function instrumentExecution(): BelongsTo
    {
        return $this->belongsTo(InstrumentExecution::class);
    }

    public function horizon(): BelongsTo
    {
        return $this->belongsTo(ContextHorizon::class, 'context_horizon_id');
    }

    public function consumption(): HasOne
    {
        return $this->hasOne(ReceiptConsumption::class);
    }

    public function receiptable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reinforcement(): HasOne
    {
        return $this->hasOne(Reinforcement::class);
    }
}
