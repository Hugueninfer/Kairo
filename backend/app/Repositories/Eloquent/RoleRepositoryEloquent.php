<?php

namespace App\Repositories\Eloquent;

use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Papéis por empresa. Toda operação recebe a empresa explicitamente e ajusta o time do
 * spatie/laravel-permission para ela, porque algumas rodam fora do contexto de tenant
 * (criar empresa, aceitar convite).
 */
class RoleRepositoryEloquent extends BaseRepository implements RoleRepository
{
    public function model(): string
    {
        return Role::class;
    }

    public function forCompany(Company $company): Collection
    {
        return $this->query($company)
            ->with('permissions:id,name')
            ->withCount('members')
            ->orderByRaw('system_key is null')
            ->orderBy('id')
            ->get();
    }

    public function findInCompany(Company $company, int $id): ?Role
    {
        return $this->query($company)->whereKey($id)->first();
    }

    public function findSystemRole(Company $company, SystemRole $key): Role
    {
        return $this->query($company)->where('system_key', $key)->firstOrFail();
    }

    public function nameExists(Company $company, string $name, ?int $ignoreId = null): bool
    {
        return $this->query($company)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function createForCompany(Company $company, string $name, array $permissions, ?SystemRole $systemKey = null): Role
    {
        return $this->inTeam($company, function () use ($company, $name, $permissions, $systemKey): Role {
            return DB::transaction(function () use ($company, $name, $permissions, $systemKey): Role {
                /** @var Role $role */
                $role = Role::query()->create([
                    'company_id' => $company->id,
                    'name' => $name,
                    'guard_name' => User::PERMISSION_GUARD,
                    'system_key' => $systemKey,
                ]);

                $role->syncPermissions($permissions);

                return $role;
            });
        });
    }

    public function updateRole(Role $role, string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $role->update(['name' => $name]);
            $role->syncPermissions($permissions);

            return $role->load('permissions:id,name')->loadCount('members');
        });
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    public function membersCount(Role $role): int
    {
        return $role->members()->count();
    }

    public function assign(User $user, Role $role): void
    {
        $this->inTeam($role->company_id, function () use ($user, $role): void {
            $user->syncRoles([$role]);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        });
    }

    public function roleOf(User $user, Company $company): ?Role
    {
        return $this->query($company)
            ->whereHas('members', fn ($query) => $query->whereKey($user->id))
            ->first();
    }

    public function revokeAll(User $user, Company $company): void
    {
        $this->inTeam($company, function () use ($user): void {
            $user->syncRoles([]);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        });
    }

    public function permissionNamesOf(User $user, Company $company): array
    {
        $role = $this->roleOf($user, $company);

        if ($role === null) {
            return [];
        }

        /** @var list<string> */
        return $role->permissions()->pluck('name')->sort()->values()->all();
    }

    /**
     * @return Builder<Role>
     */
    private function query(Company $company): Builder
    {
        return Role::query()->where('company_id', $company->id);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inTeam(Company|int $company, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($company instanceof Company ? $company->id : $company);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
