<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SystemBootstrapService
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function bootstrap(array $attributes): User
    {
        return Cache::lock('commons:system-bootstrap', 10)
            ->block(5, function () use ($attributes): User {
                if (User::query()->exists()) {
                    throw new ConflictHttpException(
                        'The Commons has already been bootstrapped.',
                    );
                }

                return DB::transaction(function () use ($attributes): User {
                    $permissions = collect(
                        config('authorization.permissions', []),
                    )->map(
                        fn (string $description, string $name): Permission =>
                            Permission::query()->create([
                                'name' => $name,
                                'description' => $description,
                            ]),
                    );

                    $administrator = Role::query()->create([
                        'name' => config(
                            'authorization.roles.system_administrator',
                        ),
                        'scope' => 'system',
                        'description' => 'Unrestricted system-level access.',
                    ]);
                    $administrator->permissions()->sync(
                        $permissions->modelKeys(),
                    );

                    $participant = Role::query()->create([
                        'name' => config(
                            'authorization.roles.participant',
                        ),
                        'scope' => 'substrate',
                        'description' => 'Act within an joined substrate.',
                    ]);
                    $participantPermission = $permissions->firstWhere(
                        'name',
                        'participants.act',
                    );
                    $participant->permissions()->sync(
                        $participantPermission === null
                            ? []
                            : [$participantPermission->getKey()],
                    );

                    $user = User::query()->create($attributes);
                    $user->roles()->attach($administrator, [
                        'substrate_id' => null,
                    ]);

                    return $user;
                });
            });
    }
}
