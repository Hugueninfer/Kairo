<?php

namespace App\DTOs\Roles;

use App\DTOs\Data;
use App\Enums\Permission;

final readonly class RoleData extends Data
{
    /**
     * @param  list<Permission>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions,
    ) {}

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_values(array_unique(array_map(fn (Permission $permission) => $permission->value, $this->permissions)));
    }
}
