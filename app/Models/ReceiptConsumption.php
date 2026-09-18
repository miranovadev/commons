<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The unique attachment is the authority for availability; evidence never disappears. */
class ReceiptConsumption extends Model
{
    protected $primaryKey = 'receipt_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['consumed_at' => 'immutable_datetime', 'legacy' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \DomainException('Receipt consumption is permanent.'));
        static::deleting(fn () => throw new \DomainException('Receipt consumption is permanent.'));
    }
}
