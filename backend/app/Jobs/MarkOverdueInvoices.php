<?php

namespace App\Jobs;

use App\Services\Billing\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Roda todo dia pelo Scheduler e marca como vencidas as faturas em aberto depois do vencimento.
 */
class MarkOverdueInvoices implements ShouldQueue
{
    use Queueable;

    public function handle(InvoiceService $invoices): void
    {
        $overdue = $invoices->markOverdue();

        if ($overdue > 0) {
            Log::info('Faturas vencidas', ['total' => $overdue]);
        }
    }
}
