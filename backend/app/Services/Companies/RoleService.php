<?php

namespace App\Services\Companies;

use App\DTOs\Roles\RoleData;
use App\Enums\AuditSubject;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Exceptions\Companies\NotCompanyMemberException;
use App\Exceptions\Roles\OwnerRoleLockedException;
use App\Exceptions\Roles\OwnerRoleReservedException;
use App\Exceptions\Roles\RoleInUseException;
use App\Exceptions\Roles\RoleNameTakenException;
use App\Exceptions\Roles\SystemRoleLockedException;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\PermissionRepository;
use App\Repositories\Contracts\RoleRepository;
use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Papéis e permissões de cada empresa.
 *
 * Cada membro tem um papel por empresa. O dono da empresa tem o papel "Dono", que
 * é exclusivo dele, e sempre passa em qualquer checagem de permissão (Gate::before).
 */
class RoleService
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly PermissionRepository $permissions,
        private readonly CompanyRepository $companies,
        private readonly AuditService $audit,
    ) {}

    /**
     * Cria os papéis padrão da empresa e dá o papel de dono a quem a criou.
     */
    public function setupCompany(Company $company, User $owner): void
    {
        $this->permissions->syncCatalog(array_map(fn (Permission $p) => $p->value, Permission::cases()));

        foreach (SystemRole::cases() as $systemRole) {
            $role = $this->roles->createForCompany(
                $company,
                $systemRole->label(),
                $systemRole->defaultPermissionNames(),
                $systemRole,
            );

            if ($systemRole === SystemRole::Owner) {
                $this->roles->assign($owner, $role);
            }
        }
    }

    /**
     * @return Collection<int, Role>
     */
    public function list(Company $company): Collection
    {
        return $this->roles->forCompany($company);
    }

    /**
     * Permissões agrupadas para montar a matriz no front.
     *
     * @return array<int, array{group: string, permissions: array<int, array{key: string, label: string}>}>
     */
    public function catalog(): array
    {
        $catalog = [];
        $positions = [];

        foreach (Permission::cases() as $permission) {
            $group = $permission->group();

            if (! isset($positions[$group])) {
                $positions[$group] = count($catalog);
                $catalog[] = ['group' => $group, 'permissions' => []];
            }

            $catalog[$positions[$group]]['permissions'][] = ['key' => $permission->value, 'label' => $permission->label()];
        }

        return $catalog;
    }

    public function find(Company $company, int $id): Role
    {
        return $this->roles->findInCompany($company, $id) ?? throw (new ModelNotFoundException)->setModel(Role::class, [$id]);
    }

    public function create(Company $company, RoleData $data): Role
    {
        if ($this->roles->nameExists($company, $data->name)) {
            throw new RoleNameTakenException;
        }

        $role = $this->roles->createForCompany($company, $data->name, $data->permissionNames());
        $this->audit->record($company->id, AuditSubject::Role, 'created', $role->name, new: ['permissions' => $this->labels($data->permissionNames())]);

        return $role->load('permissions:id,name')->loadCount('members');
    }

    /**
     * Papéis padrão mantêm o nome; só as permissões mudam. O de dono não muda.
     */
    public function update(Company $company, Role $role, RoleData $data): Role
    {
        if ($role->isOwnerRole()) {
            throw new OwnerRoleLockedException;
        }

        $name = $role->isSystem() ? $role->name : $data->name;

        if ($this->roles->nameExists($company, $name, $role->id)) {
            throw new RoleNameTakenException;
        }

        $before = $this->roles->permissionNamesOfRole($role);
        $oldName = $role->name;
        $updated = $this->roles->updateRole($role, $name, $data->permissionNames());

        $added = array_values(array_diff($data->permissionNames(), $before));
        $removed = array_values(array_diff($before, $data->permissionNames()));
        $changes = array_filter([
            'name' => $oldName === $name ? null : [$oldName, $name],
            'permissions_added' => $added === [] ? null : [null, $this->labels($added)],
            'permissions_removed' => $removed === [] ? null : [null, $this->labels($removed)],
        ]);

        if ($changes !== []) {
            $this->audit->record(
                $company->id,
                AuditSubject::Role,
                'updated',
                $name,
                array_map(fn (array $change) => $change[0], $changes),
                array_map(fn (array $change) => $change[1], $changes),
            );
        }

        return $updated;
    }

    public function delete(Role $role): void
    {
        if ($role->isSystem()) {
            throw new SystemRoleLockedException;
        }

        if ($this->roles->membersCount($role) > 0) {
            throw new RoleInUseException;
        }

        $this->roles->deleteRole($role);
        $this->audit->record((int) $role->company_id, AuditSubject::Role, 'deleted', $role->name);
    }

    /**
     * Papel com que alguém entra na empresa (convite aceito). Sem papel definido, entra como membro.
     */
    public function assignOnJoin(User $user, Company $company, ?Role $role): void
    {
        if ($role !== null && $role->company_id !== $company->id) {
            $role = null;
        }

        $this->roles->assign($user, $role ?? $this->roles->findSystemRole($company, SystemRole::Member));
    }

    public function changeMemberRole(Company $company, User $member, int $roleId): void
    {
        $role = $this->find($company, $roleId);

        if (! $this->companies->isMember($company, $member)) {
            throw new NotCompanyMemberException;
        }

        $this->ensureAssignable($company, $role);

        if ($company->isOwnedBy($member)) {
            throw new OwnerRoleReservedException;
        }

        $previous = $this->roles->roleOf($member, $company);
        $this->roles->assign($member, $role);

        if ($previous?->id !== $role->id) {
            $this->audit->record($company->id, AuditSubject::Member, 'updated', $member->name, ['role' => $previous?->name], ['role' => $role->name]);
        }
    }

    /**
     * O papel de dono não pode ser dado a ninguém por convite ou troca de papel.
     */
    public function ensureAssignable(Company $company, Role $role): void
    {
        if ($role->company_id !== $company->id) {
            throw new NotCompanyMemberException;
        }

        if ($role->isOwnerRole()) {
            throw new OwnerRoleReservedException;
        }
    }

    public function revoke(User $user, Company $company): void
    {
        $this->roles->revokeAll($user, $company);
    }

    public function roleOf(User $user, Company $company): ?Role
    {
        return $this->roles->roleOf($user, $company);
    }

    /**
     * @return list<string>
     */
    public function permissionsOf(User $user, Company $company): array
    {
        if ($company->isOwnedBy($user)) {
            return array_map(fn (Permission $p) => $p->value, Permission::cases());
        }

        return $this->roles->permissionNamesOf($user, $company);
    }

    /**
     * Nomes das permissões em português, para o histórico.
     *
     * @param  list<string>  $names
     */
    private function labels(array $names): string
    {
        $labels = array_map(fn (string $name) => Permission::tryFrom($name)?->label() ?? $name, $names);
        sort($labels);

        return implode(', ', $labels);
    }
}
