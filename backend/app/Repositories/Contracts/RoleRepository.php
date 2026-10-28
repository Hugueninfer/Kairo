<?php

namespace App\Repositories\Contracts;

use App\Enums\SystemRole;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Prettus\Repository\Contracts\RepositoryInterface;

interface RoleRepository extends RepositoryInterface
{
    /**
     * Papéis da empresa com permissões e quantidade de membros.
     *
     * @return Collection<int, Role>
     */
    public function forCompany(Company $company): Collection;

    public function findInCompany(Company $company, int $id): ?Role;

    public function findSystemRole(Company $company, SystemRole $key): Role;

    public function nameExists(Company $company, string $name, ?int $ignoreId = null): bool;

    /**
     * @param  list<string>  $permissions
     */
    public function createForCompany(Company $company, string $name, array $permissions, ?SystemRole $systemKey = null): Role;

    /**
     * @param  list<string>  $permissions
     */
    public function updateRole(Role $role, string $name, array $permissions): Role;

    public function deleteRole(Role $role): void;

    /**
     * @return list<string>
     */
    public function permissionNamesOfRole(Role $role): array;

    public function membersCount(Role $role): int;

    /**
     * Troca o papel do usuário na empresa do papel (cada membro tem um papel por empresa).
     */
    public function assign(User $user, Role $role): void;

    public function roleOf(User $user, Company $company): ?Role;

    public function revokeAll(User $user, Company $company): void;

    /**
     * @return list<string>
     */
    public function permissionNamesOf(User $user, Company $company): array;
}
