<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ListAuditRequest;
use App\Http\Resources\ActivityResource;
use App\Services\Audit\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
