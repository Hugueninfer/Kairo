<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\UpdateMemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Services\Companies\MembershipService;
use App\Services\Companies\RoleService;
use App\Support\TenantContext;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Equipe', weight: 3)]
class MemberController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function index(TenantContext $tenant): JsonResponse
    {
        $company = $tenant->company();

        return response()->json([
            'data' => $this->memberships->members($company)
                ->map(fn (User $member) => new MemberResource($member, $company)),
        ]);
    }

    public function updateRole(UpdateMemberRoleRequest $request, TenantContext $tenant, RoleService $roles, User $member): Response
    {
        $roles->changeMemberRole($tenant->company(), $member, $request->roleId());

        return response()->noContent();
    }

    public function destroy(TenantContext $tenant, User $member): Response
    {
        $this->memberships->remove($tenant->company(), $member);

        return response()->noContent();
    }
}
