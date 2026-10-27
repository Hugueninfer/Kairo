<?php

namespace App\Integrations\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Integrations\Payments\Contracts\PaymentGateway;
use App\Integrations\Payments\Data\Charge;
use App\Integrations\Payments\Data\ChargeRequest;
use App\Integrations\Payments\Data\GatewayEvent;
use App\Integrations\Payments\Exceptions\InvalidWebhookException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Gateway simulado, para desenvolvimento e testes. O link de pagamento abre uma página
 * local (fora de produção) que dispara o mesmo webhook que um gateway real dispararia.
 */
class FakeGateway implements PaymentGateway
{
    public function __construct(private readonly string $webhookToken) {}

    public function name(): string
    {
        return 'fake';
    }

    public function createCharge(ChargeRequest $request): Charge
    {
        $id = 'fake_'.Str::lower(Str::ulid()->toBase32());

        return new Charge($id, route('fake-checkout.show', ['charge' => $id, 'amount' => $request->amount, 'ref' => $request->reference]));
    }

    public function cancelCharge(string $chargeId): void {}

    public function parseWebhook(Request $request): GatewayEvent
    {
        if (! hash_equals($this->webhookToken, (string) $request->header('X-Fake-Token'))) {
            throw new InvalidWebhookException;
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        return $this->parsePayload($payload);
    }

    public function parsePayload(array $payload): GatewayEvent
    {
        $paid = ($payload['event'] ?? null) === 'payment.paid';

        return new GatewayEvent(
            id: (string) ($payload['id'] ?? ''),
            type: (string) ($payload['event'] ?? 'unknown'),
            payload: $payload,
            confirmsPayment: $paid,
            chargeId: isset($payload['charge']) ? (string) $payload['charge'] : null,
            paymentId: isset($payload['payment']) ? (string) $payload['payment'] : null,
            amount: isset($payload['amount']) ? number_format((float) $payload['amount'], 2, '.', '') : null,
            method: PaymentMethod::tryFrom((string) ($payload['method'] ?? '')) ?? PaymentMethod::Pix,
            paidAt: $paid ? Carbon::now() : null,
        );
    }
}
