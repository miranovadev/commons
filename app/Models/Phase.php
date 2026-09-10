<?php

namespace App\Models;

use App\Enums\PhaseKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Phase extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        $invalidate = function (self $phase): void {
            // A new observation can affect either side of a lineage comparison.
            // Invalidate disposable views in this substrate; immutable snapshots remain intact.
            PropositionMeasurement::query()->whereHas('proposition.phase.cycle.participant',
                fn ($query) => $query->where('substrate_id', $phase->cycle->participant->substrate_id),
            )->update(['calculator_version' => null]);
        };
        static::created(function (self $phase) use ($invalidate): void {
            if ($phase->gate_proposition_id !== null) {
                $invalidate($phase);
            }
        });
        static::updated(function (self $phase) use ($invalidate): void {
            if ($phase->wasChanged('gate_proposition_id')) {
                $invalidate($phase);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => PhaseKind::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Proposition::class, 'gate_proposition_id');
    }

    public function propositions(): HasMany
    {
        return $this->hasMany(Proposition::class);
    }

    public function supersessions(): HasMany
    {
        return $this->hasMany(Supersession::class);
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(Attestation::class);
    }

    public function instrumentExecutions(): HasMany
    {
        return $this->hasMany(InstrumentExecution::class);
    }
}
