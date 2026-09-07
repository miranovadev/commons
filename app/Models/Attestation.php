<?php

namespace App\Models;

use App\Models\Concerns\HasLedgerEntry;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attestation extends Model
{
    use HasLedgerEntry, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function scopeEffective(Builder $query): void
    {
        $query->whereDoesntHave('supersededBy');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_attestation_id');
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_attestation_id');
    }
}
