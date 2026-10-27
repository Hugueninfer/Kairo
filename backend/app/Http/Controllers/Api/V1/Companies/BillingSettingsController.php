<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\BillingSettingsRequest;
use App\Http\Resources\BillingSettingsResource;
use App\Services\Companies\CompanyService;
use App\Support\TenantContext;

/**
 * Régua de lembretes de cobrança da empresa ativa.
 */
class BillingSettingsController extends Controller
{
    public function __construct(
        private readonly CompanyService $companies,
        private readonly TenantContext $tenant,
    ) {}

    public function show(): BillingSettingsResource
    {
        return BillingSettingsResource::make($this->tenant->company());
    }

    public function update(BillingSettingsRequest $request): BillingSettingsResource
    {
        return BillingSettingsResource::make($this->companies->updateBillingSettings($this->tenant->company(), $request->toData()));
    }
}
