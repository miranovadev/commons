<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One counted participant contribution; its Receipt remains the ledger event. */
class Reinforcement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
