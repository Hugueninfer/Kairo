<?php

namespace App\Jobs;

use App\Services\Billing\ReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lembretes do dia de uma empresa. Rodar duas vezes não duplica: cada etapa sai uma vez por fatura.
 */
class SendCompanyReminders implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $companyId) {}

    public function handle(ReminderService $reminders): void
    {
        $reminders->sendForCompany($this->companyId);
    }
}
