<?php

namespace App\Models;

use App\Models\Concerns\HasLedgerEntry;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Proposition extends Model
{
    use HasLedgerEntry, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function instrumentExecutions(): HasMany
    {
        return $this->hasMany(InstrumentExecution::class);
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(Attestation::class);
    }

    public function effectiveAttestations(): HasMany
    {
        return $this->attestations()->effective();
    }

    public function measurement(): HasOne
    {
        return $this->hasOne(PropositionMeasurement::class);
    }

    public function commitmentSnapshot(): HasOne
    {
        return $this->hasOne(CommitmentSnapshot::class);
    }

    public function contextHorizonMembership(): HasOne
    {
        return $this->hasOne(ContextHorizonMembership::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'supersessions',
            'target_proposition_id',
            'source_proposition_id',
        )->withPivot('type');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'supersessions',
            'source_proposition_id',
            'target_proposition_id',
        )->withPivot('type');
    }
}
