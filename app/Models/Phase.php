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
