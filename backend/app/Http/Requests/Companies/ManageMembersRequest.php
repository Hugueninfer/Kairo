<?php

namespace App\Http\Requests\Companies;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ações sem corpo (remover membro, cancelar convite) que exigem permissão de gestão.
 */
class ManageMembersRequest extends FormRequest
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
        return [];
    }
}
