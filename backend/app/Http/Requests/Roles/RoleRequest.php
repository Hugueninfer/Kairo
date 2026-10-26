<?php

namespace App\Http\Requests\Roles;

use App\DTOs\Roles\RoleData;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ];
    }

    public function toData(): RoleData
    {
        /** @var list<string> $permissions */
        $permissions = $this->array('permissions');

        return new RoleData(
            name: $this->string('name')->trim()->toString(),
            permissions: array_map(fn (string $value) => Permission::from($value), array_values($permissions)),
        );
    }
}
