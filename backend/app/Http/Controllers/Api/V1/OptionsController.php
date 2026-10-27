<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingType;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Enums\QuoteItemType;
use App\Enums\QuoteStatus;
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
            'quote_statuses' => QuoteStatus::options(),
            'quote_item_types' => QuoteItemType::options(),
            'contract_statuses' => ContractStatus::options(),
            'invoice_statuses' => InvoiceStatus::options(),
            'payment_methods' => PaymentMethod::options(),
        ]]);
    }
}
