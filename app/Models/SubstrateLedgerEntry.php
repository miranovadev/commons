<?php

namespace App\Models;

use App\Enums\LedgerEntryKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SubstrateLedgerEntry extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'kind' => LedgerEntryKind::class,
            'sequence' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function substrate(): BelongsTo
    {
        return $this->belongsTo(Substrate::class);
    }

    public function ledgerable(): MorphTo
    {
        return $this->morphTo();
    }
}
