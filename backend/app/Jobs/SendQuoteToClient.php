<?php

namespace App\Jobs;

use App\Services\Quotes\QuoteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gera o PDF do orçamento e envia ao cliente. Roda na fila porque gerar PDF é lento.
 */
class SendQuoteToClient implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $quoteUlid)
    {
        $this->afterCommit();
    }

    public function handle(QuoteService $quotes): void
    {
        $quotes->deliver($this->quoteUlid);
    }
}
