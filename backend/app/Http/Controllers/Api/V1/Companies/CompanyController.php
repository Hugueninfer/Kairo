<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\Companies\CompanyService;
use App\Services\Companies\RoleService;
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

    /**
     * Empresa ativa, com o papel e as permissões de quem pede (o front usa para mostrar ou esconder ações).
     */
    public function current(Request $request, TenantContext $tenant, RoleService $roles): CompanyResource
    {
        /** @var User $user */
        $user = $request->user();
        $company = $tenant->company();
        $role = $roles->roleOf($user, $company);

        return CompanyResource::make($company)->additional(['meta' => [
            'role' => $role === null ? null : ['id' => $role->id, 'name' => $role->name],
            'permissions' => $roles->permissionsOf($user, $company),
        ]]);
    }

    public function update(CompanyRequest $request, TenantContext $tenant): CompanyResource
    {
        return CompanyResource::make($this->companies->update($tenant->company(), $request->toData()));
    }
}
