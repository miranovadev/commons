<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SubstrateMembership::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('substrate_id')
            ->withTimestamps();
    }

    public function hasPermission(
        string $permission,
        ?Substrate $substrate = null,
    ): bool {
        return $this->roles()
            ->whereHas(
                'permissions',
                fn (Builder $query) => $query->where('name', $permission),
            )
            ->where(function (Builder $query) use ($substrate): void {
                $query->whereNull('role_user.substrate_id');

                if ($substrate !== null) {
                    $query->orWhere(
                        'role_user.substrate_id',
                        $substrate->getKey(),
                    );
                }
            })
            ->exists();
    }

    public function hasSystemAccess(): bool
    {
        return $this->hasPermission('system.access');
    }
}
