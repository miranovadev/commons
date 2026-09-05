<?php

namespace App\Models\Concerns;

use App\Models\SubstrateLedgerEntry;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasLedgerEntry
{
    public function ledgerEntry(): MorphOne
    {
        return $this->morphOne(SubstrateLedgerEntry::class, 'ledgerable');
    }
}
