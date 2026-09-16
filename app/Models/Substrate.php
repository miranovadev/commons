<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Substrate extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::updated(function (self $substrate): void {
            if ($substrate->wasChanged('configuration')) {
                // A receipt-weight configuration change really affects every
                // region; ordinary contributions never use this broad boundary.
                app(\App\Services\FieldMutationService::class)->mark($substrate->id,
                    $substrate->contextHorizons()->pluck('id')->all());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SubstrateMembership::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SubstrateLedgerEntry::class);
    }

    public function commitmentSnapshots(): HasMany
    {
        return $this->hasMany(CommitmentSnapshot::class);
    }

    public function contextHorizons(): HasMany
    {
        return $this->hasMany(ContextHorizon::class);
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function maximumParticipants(): ?int
    {
        $maximum = data_get($this->configuration, 'maximum_participants');

        return $maximum === null ? null : (int) $maximum;
    }
}
