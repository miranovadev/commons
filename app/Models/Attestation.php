<?php

namespace App\Models;

use App\Models\Concerns\HasLedgerEntry;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attestation extends Model
{
    use HasLedgerEntry, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }
}
