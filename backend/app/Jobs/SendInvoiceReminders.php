<?php

namespace App\Jobs;

use App\Services\Billing\ReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Roda todo dia pelo Scheduler e põe na fila a régua de lembretes de cada empresa que a deixou ligada.
 */
class SendInvoiceReminders implements ShouldQueue
{
    use Queueable;

    public function handle(ReminderService $reminders): void
    {
        foreach ($reminders->companiesToRemind() as $companyId) {
            SendCompanyReminders::dispatch($companyId);
        }
    }
}
