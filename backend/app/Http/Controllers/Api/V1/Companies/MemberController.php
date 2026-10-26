<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\ManageMembersRequest;
use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Services\Companies\MembershipService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

    public function destroy(ManageMembersRequest $request, TenantContext $tenant, User $member): Response
    {
        $this->memberships->remove($tenant->company(), $member);

        return response()->noContent();
    }
}
