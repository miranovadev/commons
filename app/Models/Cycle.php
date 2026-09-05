<?php

namespace App\Models;

use App\Enums\Phase;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cycle extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'current_phase' => Phase::class,
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Proposition::class, 'gate_proposition_id');
    }
}
