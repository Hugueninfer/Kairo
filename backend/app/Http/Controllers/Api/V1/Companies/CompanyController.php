<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\Companies\CompanyService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companies) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return CompanyResource::collection($this->companies->listForUser($user));
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $this->companies->create($user, $request->toData());

        return CompanyResource::make($company)->response()->setStatusCode(201);
    }

    public function switch(Request $request, Company $company): CompanyResource
    {
        /** @var User $user */
        $user = $request->user();

        return CompanyResource::make($this->companies->switchTo($user, $company));
    }

    public function current(TenantContext $tenant): CompanyResource
    {
        return CompanyResource::make($tenant->company());
    }

    public function update(UpdateCompanyRequest $request, TenantContext $tenant): CompanyResource
    {
        return CompanyResource::make($this->companies->update($tenant->company(), $request->toData()));
    }
}
