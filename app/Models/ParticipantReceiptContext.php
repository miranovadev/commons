<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Current context state; transitions are preserved separately as immutable events. */
class ParticipantReceiptContext extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['loaded_at' => 'immutable_datetime', 'cleared_at' => 'immutable_datetime'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cleared_at');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
