<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supersession extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Proposition::class, 'source_proposition_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Proposition::class, 'target_proposition_id');
    }
}
