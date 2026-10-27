<?php

namespace App\Jobs;

use App\Services\Billing\PaymentWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processa um aviso de pagamento já guardado (dá baixa na fatura).
 */
class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $eventId)
    {
        $this->afterCommit();
    }

    public function handle(PaymentWebhookService $webhooks): void
    {
        $webhooks->process($this->eventId);
    }
}
