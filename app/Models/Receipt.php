<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Receipt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function instrumentExecution(): BelongsTo
    {
        return $this->belongsTo(InstrumentExecution::class);
    }

    public function receiptable(): MorphTo
    {
        return $this->morphTo();
    }
}
