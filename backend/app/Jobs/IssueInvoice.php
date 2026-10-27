<?php

namespace App\Jobs;

use App\Services\Billing\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Cria a cobrança no gateway, gera o PDF e envia a fatura ao cliente.
 * Se o gateway falhar, tenta de novo com intervalos crescentes.
 */
class IssueInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly string $invoiceUlid)
    {
        $this->afterCommit();
    }

    public function handle(InvoiceService $invoices): void
    {
        $invoices->issue($this->invoiceUlid);
    }
}
