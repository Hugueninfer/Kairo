<?php

namespace App\Services\Billing;

use App\Integrations\Payments\Data\GatewayEvent;
use App\Integrations\Payments\PaymentGatewayManager;
use App\Jobs\ProcessPaymentWebhook;
use App\Repositories\Contracts\WebhookEventRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Recebe os avisos dos gateways: confere a assinatura, guarda (uma vez só) e processa na fila.
 * Responder rápido ao gateway evita que ele reenvie o aviso por timeout.
 */
class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly WebhookEventRepository $events,
        private readonly InvoiceService $invoices,
    ) {}

    public function receive(string $gateway, Request $request): void
    {
        if (! $this->gateways->has($gateway)) {
            throw new NotFoundHttpException;
        }

        $this->accept($gateway, $this->gateways->gateway($gateway)->parseWebhook($request));
    }

    /**
     * Guarda o aviso já conferido. Aviso repetido é ignorado.
     */
    public function accept(string $gateway, GatewayEvent $event): void
    {
        $stored = $this->events->storeOnce($gateway, $event->id, $event->type, $event->payload);

        if ($stored !== null) {
            ProcessPaymentWebhook::dispatch($stored->id);
        }
    }

    /**
     * Chamado pela fila. Erros ficam gravados no evento para conferência, sem travar a fila.
     */
    public function process(int $eventId): void
    {
        $stored = $this->events->findById($eventId);

        if ($stored === null || $stored->processed_at !== null) {
            return;
        }

        try {
            $event = $this->gateways->gateway($stored->gateway)->parsePayload($stored->payload);

            if ($event->confirmsPayment && $event->chargeId !== null && $event->paymentId !== null && $event->method !== null) {
                $this->invoices->confirmGatewayPayment(
                    $stored->gateway,
                    $event->chargeId,
                    $event->paymentId,
                    $event->amount,
                    $event->method,
                    $event->paidAt ?? Carbon::now(),
                );
            }

            $this->events->markProcessed($stored);
        } catch (Throwable $error) {
            Log::warning('Falha ao processar aviso de pagamento', ['event' => $eventId, 'error' => $error->getMessage()]);
            $this->events->markProcessed($stored, $error->getMessage());
        }
    }
}
