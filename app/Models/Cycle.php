<?php

namespace App\Models;

use App\Enums\RestReason;
use App\Services\ChiralityPopulationHistoryService;
use App\Services\ContextHorizonProjector;
use App\Services\FieldMutationService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

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
            if ($cycle->entry_committal_id !== null) {
                $entry = $cycle->entryCommittal()->firstOrFail();
                app(ContextHorizonProjector::class)->forEntry($entry, $horizon);
                $cycle->setRelation('entryCommittal', $entry);
            }
        });
        static::updating(function (self $cycle): void {
            if ($cycle->isDirty('context_horizon_id')) {
                throw new \DomainException('A Cycle cannot change its Context Horizon.');
            }
            if ($cycle->isDirty('entry_committal_id')) {
                throw new \DomainException('A Cycle cannot change its entry Committal.');
            }
        });
    }

    /** Entry changes live Chirality; completion retains existing Closure invalidation. */
    public function save(array $options = [])
    {
        $changesParticipation = $this->entry_committal_id !== null
            && (! $this->exists || ($this->isDirty('completed_at')
                && (($this->getRawOriginal('completed_at') === null) !== ($this->completed_at === null))));
        if (! $changesParticipation) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            Substrate::whereKey($this->participant->substrate_id)->lockForUpdate()->firstOrFail();
            $entry = ! $this->exists;
            if ($entry) {
                app(ChiralityPopulationHistoryService::class)->observe($this->participant->substrate_id);
            }
            $saved = parent::save($options);
            if ($saved) {
                // Dirty only Horizons containing Cycles entered at this basin.
                // The existing field version remains the publication guard.
                $horizons = self::where('entry_committal_id', $this->entry_committal_id)
                    ->distinct()->pluck('context_horizon_id')->all();
                app(FieldMutationService::class)->mark($this->participant->substrate_id, $horizons, [$this->entry_committal_id]);
            }
            if ($saved && $entry) {
                app(ChiralityPopulationHistoryService::class)->observe($this->participant->substrate_id);
            }

            return $saved;
        });
    }

    public function contextHorizon(): BelongsTo
    {
        return $this->belongsTo(ContextHorizon::class);
    }

    public function entryCommittal(): BelongsTo
    {
        return $this->belongsTo(Proposition::class, 'entry_committal_id');
    }

    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'rested_at' => 'immutable_datetime',
            'rest_reason' => RestReason::class,
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
