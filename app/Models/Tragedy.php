<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** First no-Potential Rest in a Horizon. Legacy Origin-lineage records remain archived separately. */
class Tragedy extends Model
{
    use HasUlids;

    protected $table = 'horizon_tragedies';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \DomainException('Tragedy history is immutable.'));
        static::deleting(fn () => throw new \DomainException('Tragedy history is immutable.'));
    }
}
