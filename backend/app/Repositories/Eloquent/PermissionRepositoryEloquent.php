<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\PermissionRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionRepositoryEloquent extends BaseRepository implements PermissionRepository
{
    public function model(): string
    {
        return Permission::class;
    }

    public function syncCatalog(array $names): void
    {
        $existing = Permission::query()->whereIn('name', $names)->pluck('name')->all();
        $missing = array_values(array_diff($names, $existing));

        if ($missing === []) {
            return;
        }

        $guard = User::PERMISSION_GUARD;
        $now = now();

        Permission::query()->insert(array_map(
            fn (string $name) => ['name' => $name, 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now],
            $missing,
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
