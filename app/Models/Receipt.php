<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasUlids;

    public $keyType = 'string';

    public $incrementing = false;
}
