<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }
}
