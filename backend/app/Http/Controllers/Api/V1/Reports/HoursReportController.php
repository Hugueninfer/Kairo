<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\HoursReportRequest;
use App\Services\Reports\ReportService;
use App\Support\TenantContext;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * Relatório de horas. Enquanto a fila calcula, responde 202 e o front pergunta de novo.
 */
#[Group('Painel e relatórios', weight: 14)]
class HoursReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function __invoke(HoursReportRequest $request, TenantContext $tenant): JsonResponse
    {
        $report = $this->reports->hours($tenant->company(), $request->toFilters());

        return response()->json($report, $report['status'] === 'ready' ? 200 : 202);
    }
}
