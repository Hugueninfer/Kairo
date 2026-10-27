<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos dos gateways de pagamento. Sem login: a autenticidade é conferida pelo adaptador
 * do gateway (token ou assinatura). Responde rápido; o processamento vai para a fila.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentWebhookService $webhooks) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $this->webhooks->receive($gateway, $request);

        return response()->json(['received' => true]);
    }
}
