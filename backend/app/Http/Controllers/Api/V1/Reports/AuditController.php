<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ListAuditRequest;
use App\Http\Resources\ActivityResource;
use App\Services\Audit\AuditService;
use App\Support\TenantContext;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Histórico', weight: 15)]
class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly TenantContext $tenant,
    ) {}

    public function index(ListAuditRequest $request): AnonymousResourceCollection
    {
        return ActivityResource::collection($this->audit->list($this->tenant->company(), $request->toFilters()));
    }
}
