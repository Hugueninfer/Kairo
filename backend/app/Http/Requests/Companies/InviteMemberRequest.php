<?php

namespace App\Http\Requests\Companies;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('company_id', $tenant->id())],
        ];
    }

    public function email(): string
    {
        return mb_strtolower(trim($this->string('email')->toString()));
    }

    public function roleId(): ?int
    {
        return $this->filled('role_id') ? $this->integer('role_id') : null;
    }
}
