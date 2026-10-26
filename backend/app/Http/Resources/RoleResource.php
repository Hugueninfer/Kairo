<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'system_key' => $this->system_key?->value,
            'is_system' => $this->isSystem(),
            'is_locked' => $this->isOwnerRole(),
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->map(fn (Permission $permission) => $permission->name)->sort()->values(),
            ),
            'members_count' => $this->whenCounted('members'),
        ];
    }
}
