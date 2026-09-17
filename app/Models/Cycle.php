<?php

namespace App\Models;

use App\Services\ContextHorizonProjector;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cycle extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $cycle): void {
            // Resolve the default once, at creation. Operational entry supplies its
            // selected lineage explicitly; an existing Cycle never consults this default.
            $horizon = $cycle->context_horizon_id === null
                ? app(ContextHorizonProjector::class)->currentFor($cycle->participant->substrate)
                : $cycle->contextHorizon()->firstOrFail();
            if ($horizon->substrate_id !== $cycle->participant->substrate_id) {
                throw new \DomainException('A new Cycle requires a Context Horizon in its own substrate.');
            }
            $cycle->context_horizon_id = $horizon->id;
            $cycle->setRelation('contextHorizon', $horizon);
        });
        static::updating(function (self $cycle): void {
            if ($cycle->isDirty('context_horizon_id')) {
                throw new \DomainException('A Cycle cannot change its Context Horizon.');
            }
        });
    }

    public function contextHorizon(): BelongsTo
    {
        return $this->belongsTo(ContextHorizon::class);
    }

    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'rested_at' => 'immutable_datetime',
            'rest_reason' => \App\Enums\RestReason::class,
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }

    public function currentPhase(): HasOne
    {
        return $this->hasOne(Phase::class)
            ->whereNotNull('started_at')
            ->ofMany('kind', 'max');
    }
}
