<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropositionMeasurement extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'proposition_id';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'correspondence' => 'float',
            'shape_topology' => 'float',
            'phase_profile' => 'float',
            'chirality' => 'float',
            'orientation' => 'float',
            'attestation_cadence' => 'float',
            'closure' => 'float',
            'calculated_at' => 'immutable_datetime',
        ];
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }
}

