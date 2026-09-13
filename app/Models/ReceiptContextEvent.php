<?php

namespace App\Models;

use App\Models\Concerns\HasLedgerEntry;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptContextEvent extends Model
{
    use HasLedgerEntry, HasUlids;

    public const UPDATED_AT = null;

    public function context(): BelongsTo
    {
        return $this->belongsTo(ParticipantReceiptContext::class, 'participant_receipt_context_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }
}
