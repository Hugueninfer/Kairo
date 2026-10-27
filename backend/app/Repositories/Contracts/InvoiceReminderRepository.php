<?php

namespace App\Repositories\Contracts;

use App\Enums\ReminderStage;
use App\Models\Invoice;
use Prettus\Repository\Contracts\RepositoryInterface;

interface InvoiceReminderRepository extends RepositoryInterface
{
    /**
     * Registra o lembrete da etapa. Devolve false se essa etapa já tinha sido enviada.
     */
    public function recordOnce(Invoice $invoice, ReminderStage $stage): bool;
}
