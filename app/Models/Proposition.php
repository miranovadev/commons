<?php

namespace App\Models;

use App\Support\Epithet;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Proposition extends Model
{
    use HasUlids;

    public $keyType = 'string';

    public $incrementing = false;

    public $appends = [
        'epithet',
    ];

    public function getEpithetAttribute() {
        return Epithet::for($this->tier, $this->register);
    }
}
