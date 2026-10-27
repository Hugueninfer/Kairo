<?php

namespace App\Repositories\Eloquent;

use App\Enums\ReminderStage;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Repositories\Contracts\InvoiceReminderRepository;

class InvoiceReminderRepositoryEloquent extends BaseRepository implements InvoiceReminderRepository
{
    public function model(): string
    {
        return InvoiceReminder::class;
    }

    public function recordOnce(Invoice $invoice, ReminderStage $stage): bool
    {
        // O índice único (fatura + etapa) garante um lembrete só, mesmo com o job rodando duas vezes.
        $reminder = InvoiceReminder::query()->createOrFirst(
            ['invoice_id' => $invoice->id, 'stage' => $stage],
            ['company_id' => $invoice->company_id, 'sent_at' => now()],
        );

        return $reminder->wasRecentlyCreated;
    }
}
