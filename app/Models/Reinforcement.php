<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historical verification allocation. Active Reinforcement is derived from ParticipantReceiptContext. */
class Reinforcement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
