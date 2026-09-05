<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContextHorizonMembership extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'proposition_id';

    protected $keyType = 'string';

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function horizon(): BelongsTo
    {
        return $this->belongsTo(
            ContextHorizon::class,
            'context_horizon_id',
        );
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            SubstrateLedgerEntry::class,
            'ledger_entry_id',
        );
    }
}
