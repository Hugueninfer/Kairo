<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Companies\RoleService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(TenantContext $tenant): AnonymousResourceCollection
    {
        return RoleResource::collection($this->roles->list($tenant->company()));
    }

    public function permissions(): JsonResponse
    {
        return response()->json(['data' => $this->roles->catalog()]);
    }

    public function store(RoleRequest $request, TenantContext $tenant): JsonResponse
    {
        $role = $this->roles->create($tenant->company(), $request->toData());

        return RoleResource::make($role)->response()->setStatusCode(201);
    }

    public function update(RoleRequest $request, TenantContext $tenant, Role $role): RoleResource
    {
        return RoleResource::make($this->roles->update($tenant->company(), $role, $request->toData()));
    }

    public function destroy(Role $role): Response
    {
        $this->roles->delete($role);

        return response()->noContent();
    }
}
