<?php

namespace App\Http\Requests\Companies;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(TenantContext $tenant): bool
    {
        return (bool) $this->user()?->can('manageMembers', $tenant->company());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function email(): string
    {
        return mb_strtolower(trim($this->string('email')->toString()));
    }
}
