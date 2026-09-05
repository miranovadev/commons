<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposition extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }
}
