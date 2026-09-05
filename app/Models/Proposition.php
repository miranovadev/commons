<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proposition extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function instrumentExecutions(): HasMany
    {
        return $this->hasMany(InstrumentExecution::class);
    }
}
