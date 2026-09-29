<?php

namespace App\Models;

use App\Enums\ContextDomain;
use App\Enums\ObservableDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitmentSnapshot extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'proposition_id';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'correspondence' => 'float',
            'shape_topology' => 'float',
            'cardinal_position' => 'float',
            'chirality' => 'float',
            'orientation' => 'float',
            'attestation_cadence' => 'float',
            'closure' => 'float',
            'depth' => 'float',
            'cadence' => 'float',
            'cadence_interval' => 'integer',
            'depth_direction' => ObservableDirection::class,
            'cadence_direction' => ObservableDirection::class,
            'tension_before' => 'float',
            'prominence' => 'float',
            'turbulence' => 'float',
            'tension' => 'float',
            'direction_before' => ContextDomain::class,
            'direction_after' => ContextDomain::class,
            'evaluated_at' => 'immutable_datetime',
        ];
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    public function substrate(): BelongsTo
    {
        return $this->belongsTo(Substrate::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            SubstrateLedgerEntry::class,
            'ledger_entry_id',
        );
    }

    public function supersession(): BelongsTo
    {
        return $this->belongsTo(Supersession::class);
    }

    public function isClosureBoundary(): bool
    {
        return $this->direction_before !== $this->direction_after;
    }
}
