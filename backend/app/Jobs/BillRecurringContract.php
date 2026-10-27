<?php

namespace App\Jobs;

use App\Services\Billing\RecurringBillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gera (e envia, se o contrato pede) as faturas recorrentes de um contrato.
 * Rodar duas vezes não duplica: cada período só tem uma fatura.
 */
class BillRecurringContract implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public function __construct(public readonly int $contractId) {}

    public function handle(RecurringBillingService $billing): void
    {
        $billing->bill($this->contractId);
    }
}
