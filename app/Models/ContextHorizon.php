<?php

namespace App\Models;

use App\Enums\ContextDomain;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContextHorizon extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'domain' => ContextDomain::class,
            'sequence' => 'integer',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function substrate(): BelongsTo
    {
        return $this->belongsTo(Substrate::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(
            CommitmentSnapshot::class,
            'opened_by_proposition_id',
            'proposition_id',
        );
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(
            CommitmentSnapshot::class,
            'closed_by_proposition_id',
            'proposition_id',
        );
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ContextHorizonMembership::class);
    }

    public function propositions(): BelongsToMany
    {
        return $this->belongsToMany(
            Proposition::class,
            'context_horizon_memberships',
        )->withPivot(['ledger_entry_id', 'projected_at']);
    }
}
