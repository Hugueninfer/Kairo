<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingType;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Opções dos selects do front, direto dos Enums (uma fonte só para valores e rótulos).
 */
class OptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'project_statuses' => ProjectStatus::options(),
            'billing_types' => BillingType::options(),
            'task_statuses' => TaskStatus::options(),
            'task_priorities' => TaskPriority::options(),
        ]]);
    }
}
