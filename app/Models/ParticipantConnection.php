<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantConnection extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'configuration' => 'array',
            'next_trigger_at' => 'immutable_datetime',
            'last_triggered_at' => 'immutable_datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
