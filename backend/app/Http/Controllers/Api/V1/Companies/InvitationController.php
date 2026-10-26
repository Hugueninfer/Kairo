<?php

namespace App\Http\Controllers\Api\V1\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\InviteMemberRequest;
use App\Http\Requests\Companies\ManageMembersRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\InvitationPreviewResource;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Companies\InvitationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function index(ManageMembersRequest $request): AnonymousResourceCollection
    {
        return InvitationResource::collection($this->invitations->pending());
    }

    public function store(InviteMemberRequest $request, TenantContext $tenant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invitation = $this->invitations->invite($tenant->company(), $user, $request->email());

        return InvitationResource::make($invitation)->response()->setStatusCode(201);
    }

    public function destroy(ManageMembersRequest $request, Invitation $invitation): Response
    {
        $this->invitations->revoke($invitation);

        return response()->noContent();
    }

    public function show(string $token): InvitationPreviewResource
    {
        return InvitationPreviewResource::make($this->invitations->findByToken($token));
    }

    public function accept(Request $request, string $token): CompanyResource
    {
        /** @var User $user */
        $user = $request->user();

        return CompanyResource::make($this->invitations->accept($token, $user));
    }
}
