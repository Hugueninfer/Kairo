<?php

namespace App\Integrations\Payments\Contracts;

use App\Integrations\Payments\Data\Charge;
use App\Integrations\Payments\Data\ChargeRequest;
use App\Integrations\Payments\Data\GatewayEvent;
use App\Integrations\Payments\Exceptions\GatewayException;
use App\Integrations\Payments\Exceptions\InvalidWebhookException;
use Illuminate\Http\Request;

/**
 * Contrato de um gateway de pagamento. Cada provedor tem um adaptador; o resto do
 * Kairo só conhece esta interface. O cartão do cliente nunca passa pelo Kairo: o
 * pagamento acontece na página do gateway.
 */
interface PaymentGateway
{
    /**
     * Nome gravado na fatura e usado na URL do webhook (ex.: "asaas").
     */
    public function name(): string;

    /**
     * @throws GatewayException
     */
    public function createCharge(ChargeRequest $request): Charge;

    /**
     * @throws GatewayException
     */
    public function cancelCharge(string $chargeId): void;

    /**
     * Confere a autenticidade do aviso e traduz o corpo.
     *
     * @throws InvalidWebhookException
     */
    public function parseWebhook(Request $request): GatewayEvent;

    /**
     * Traduz um corpo de aviso já conferido e guardado (usado pela fila).
     *
     * @param  array<string, mixed>  $payload
     */
    public function parsePayload(array $payload): GatewayEvent;
}
