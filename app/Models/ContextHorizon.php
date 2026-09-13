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

    protected static function booted(): void
    {
        static::saving(function (self $horizon): void {
            if ($horizon->exists && $horizon->isDirty(['parent_context_horizon_id', 'domain', 'opened_by_proposition_id', 'substrate_id'])) {
                throw new \DomainException('A Context Horizon cannot change its causal lineage.');
            }
            if (! $horizon->exists) {
                if ($horizon->parent_context_horizon_id === null) {
                    if ($horizon->sequence !== 1 || $horizon->domain !== ContextDomain::Emergence || $horizon->opened_by_proposition_id !== null
                        || self::where('substrate_id', $horizon->substrate_id)->exists()) {
                        throw new \DomainException('Only the initial Emergence Horizon can be a root.');
                    }
                } else {
                    $parent = $horizon->parent()->firstOrFail();
                    if ($parent->substrate_id !== $horizon->substrate_id || $horizon->domain !== $parent->domain->opposite()) {
                        throw new \DomainException('A child Horizon must alternate Domain within its parent substrate.');
                    }
                    $horizon->predecessorCommitment();
                }
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_context_horizon_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_context_horizon_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    /** The causal boundary snapshot belongs to the immediate parent, never a sibling. */
    public function predecessorCommitment(): ?CommitmentSnapshot
    {
        if ($this->parent_context_horizon_id === null) {
            if ($this->sequence !== 1 || $this->opened_by_proposition_id !== null || $this->domain !== ContextDomain::Emergence) {
                throw new \DomainException('Only the Origin Horizon may use bootstrap comparison assumptions.');
            }

            return null;
        }
        $snapshot = $this->openedBy()->with('proposition.phase.cycle')->first();
        $parent = $this->parent()->firstOrFail();
        if ($snapshot === null || $snapshot->direction_before !== $parent->domain || $snapshot->direction_after !== $this->domain
            || $snapshot->proposition->phase->cycle->context_horizon_id !== $parent->id
            || $parent->substrate_id !== $this->substrate_id || $this->domain !== $parent->domain->opposite()) {
            throw new \DomainException('A Horizon requires its own parent boundary commitment.');
        }

        return $snapshot;
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
