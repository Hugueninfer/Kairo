<?php

namespace App\Http\Requests\Companies;

use App\Support\TenantContext;

class UpdateCompanyRequest extends CompanyRequest
{
    public function authorize(TenantContext $tenant): bool
    {
        return (bool) $this->user()?->can('update', $tenant->company());
    }
}
