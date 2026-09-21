<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** First evidenced no-Potential Rest in a Commit Region. Null governing IDs retain archived Horizon proof. */
class Tragedy extends Model
{
    use HasUlids;

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
