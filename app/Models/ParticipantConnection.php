<?php

namespace App\Models;

use App\Enums\ConnectionProvider;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantConnection extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ConnectionProvider::class,
            'is_enabled' => 'boolean',
            'configuration' => 'array',
            'credentials' => 'encrypted:array',
            'last_triggered_at' => 'immutable_datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function lastTriggeredCycle(): BelongsTo
    {
        return $this->belongsTo(
            Cycle::class,
            'last_triggered_cycle_id',
        );
    }
}
