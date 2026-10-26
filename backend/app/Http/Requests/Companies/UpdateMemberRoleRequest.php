<?php

namespace App\Http\Requests\Companies;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('company_id', $tenant->id())],
        ];
    }

    public function roleId(): int
    {
        return $this->integer('role_id');
    }
}
