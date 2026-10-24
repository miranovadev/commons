<?php

namespace App\Models;

use App\Enums\ReceiptCapability;
use App\Models\Concerns\HasLedgerEntry;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
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

    public const SPECIAL_RECEIPT_TYPES = ['tragedy'];

    public function isSpecial(): bool
    {
        return in_array($this->type, self::SPECIAL_RECEIPT_TYPES, true);
    }

    public function isEvidence(): bool
    {
        return ! $this->isSpecial();
    }

    public function scopeEvidence(Builder $query): void
    {
        $query->whereNotIn('type', self::SPECIAL_RECEIPT_TYPES);
    }

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
            if ($receipt->receiptable_type === (new Attestation)->getMorphClass()) {
                $receipt->receiptable()->associate(Attestation::findOrFail($receipt->receiptable_id)->proposition);
            }
            $execution = $receipt->evidenceExecution;
            $receipt->phase_id ??= $execution?->phase_id;
            if ($receipt->type !== 'tragedy' && $execution !== null
                && ($receipt->type !== ($execution->type === 'prepare' ? 'preparation' : $execution->kind)
                    || $receipt->phase_id !== $execution->phase_id
                    || $receipt->receiptable_type !== (new Proposition)->getMorphClass()
                    || $receipt->receiptable_id !== $execution->proposition_id)) {
                throw new DomainException('Receipt type, creating Phase and origin must match its evidence execution.');
            }
            $horizonId = $receipt->phase?->cycle->context_horizon_id;
            if ($horizonId === null || ($receipt->type !== 'tragedy' && $receipt->evidence_execution_id === null)) {
                throw new DomainException('A Receipt requires a creating action in a Horizon.');
            }
            if ($receipt->context_horizon_id !== null && $receipt->context_horizon_id !== $horizonId) {
                throw new DomainException('A Receipt belongs to its creating action’s Horizon.');
            }
            $receipt->context_horizon_id = $horizonId;
        });
        static::updating(function (self $receipt): void {
            if ($receipt->isDirty()) {
                throw new DomainException('Receipts are immutable observations. Complete an evidence execution to create a new Receipt.');
            }
        });
    }

    public function capabilityValues(): array
    {
        // Historical Cite/Hyperlink Receipts remain readable. Replay additionally
        // requires a structured originating invocation, never parsed evidence text.
        return $this->capabilities ?? ReceiptCapability::forReceipt($this->type);
    }

    public function supports(ReceiptCapability $capability): bool
    {
        return in_array($capability->value, $this->capabilityValues(), true)
            && ($capability !== ReceiptCapability::Replayable || $this->replayInvocation() !== null);
    }

    public function replayInvocation(): ?array
    {
        if ($this->type === 'tragedy') {
            return null;
        }
        $execution = $this->evidenceExecution;
        $input = $execution?->input;
        if ($execution?->type !== 'instrument' || $execution->status !== 'completed' || ! is_array($input)
            || ($input['kind'] ?? null) !== $this->type) {
            return null;
        }
        if ($this->type === 'web_search' && is_string($input['query'] ?? null)) {
            return ['kind' => 'web_search', 'query' => $input['query']];
        }
        if ($this->type === 'load_file' && ($input['method'] ?? null) === 'download' && is_string($input['reference'] ?? null)) {
            return ['kind' => 'load_file', 'method' => 'download', 'reference' => $input['reference']];
        }
        if ($this->type === 'hyperlink' && is_string($input['reference'] ?? null)) {
            return ['kind' => 'hyperlink', 'reference' => trim($input['reference'])];
        }

        return null;
    }

    public function evidenceExecution(): BelongsTo
    {
        return $this->belongsTo(EvidenceExecution::class);
    }

    public function horizon(): BelongsTo
    {
        return $this->belongsTo(ContextHorizon::class, 'context_horizon_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function observationComplete(): bool
    {
        return $this->type !== 'tragedy' && ($this->evidenceExecution?->observationRecorded() ?? false);
    }

    public function isPreparationFailure(): bool
    {
        return $this->type === 'preparation' && $this->evidenceExecution?->type === 'prepare'
            && $this->evidenceExecution->status === 'failed' && $this->evidenceExecution->outcome === 'failed'
            && ($this->metadata['outcome']['type'] ?? null) === 'failed';
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
