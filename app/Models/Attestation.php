<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Attestation extends Model
{
    use HasUlids;

    public $keyType = 'string';

    public $incrementing = false;
}
