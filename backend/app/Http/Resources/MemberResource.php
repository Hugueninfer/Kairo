<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberResource extends JsonResource
{
    public function __construct(User $resource, private readonly Company $company)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role|null $role */
        $role = $this->whenLoaded('roles', fn () => $this->roles->first(), null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_owner' => $this->company->isOwnedBy($this->resource),
            'role' => $role === null ? null : ['id' => $role->id, 'name' => $role->name],
        ];
    }
}
