<?php

namespace App\Models;

use App\Models\Concerns\HasLedgerEntry;
use App\Services\CausalContextService;
use App\Services\ExhaustionService;
use App\Services\Observables\ClosureBirthService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Proposition extends Model
{
    use HasLedgerEntry, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function save(array $options = [])
    {
        if ($this->exists) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            // Serialize birth with Cycle entry and Commit; a failed observation
            // rolls back the Proposition rather than leaving a partial birth.
            Substrate::query()->lockForUpdate()->findOrFail($this->phase->cycle->participant->substrate_id);

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::created(fn (self $proposition) => app(ClosureBirthService::class)->record($proposition));

        static::saving(function (self $proposition): void {
            if ($proposition->exists && $proposition->isDirty('phase_id')) {
                throw new \DomainException('A Proposition cannot change its creating Phase.');
            }
            if (! $proposition->exists || $proposition->isDirty('register')) {
                $causality = app(CausalContextService::class);
                $horizon = $causality->assertPhase($proposition->phase()->firstOrFail());
                $causality->assertRegister($proposition->register, $horizon);
            }
        });
    }

    public function scopeActive(Builder $query): void
    {
        app(ExhaustionService::class)->actionable($query);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function evidenceExecutions(): HasMany
    {
        return $this->hasMany(EvidenceExecution::class);
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

    /** History sources are explicit Commit edges, independent of edge strength. */
    public function commitSources(): BelongsToMany
    {
        return $this->parents()->wherePivot('type', 'commit');
    }
}
