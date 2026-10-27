<?php

namespace App\Jobs;

use App\Services\Billing\RecurringBillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Roda todo dia pelo Scheduler e põe na fila a cobrança de cada contrato recorrente que chegou na data.
 * Um job por contrato: a falha de um não segura os outros.
 */
class GenerateRecurringInvoices implements ShouldQueue
{
    use Queueable;

    public function handle(RecurringBillingService $billing): void
    {
        foreach ($billing->dueContracts() as $contractId) {
            BillRecurringContract::dispatch($contractId);
        }
    }
}
