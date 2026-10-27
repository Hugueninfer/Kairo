<?php

namespace App\Integrations\Payments;

use App\Integrations\Payments\Contracts\PaymentGateway;
use App\Integrations\Payments\Gateways\FakeGateway;
use InvalidArgumentException;

/**
 * Entrega o adaptador do gateway pelo nome (config/payments.php). Sem nome, o padrão.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function gateway(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('payments.default');

        return $this->resolved[$name] ??= $this->make($name);
    }

    public function has(string $name): bool
    {
        return is_array(config("payments.gateways.{$name}"));
    }

    private function make(string $name): PaymentGateway
    {
        /** @var array<string, mixed>|null $config */
        $config = config("payments.gateways.{$name}");

        return match (true) {
            $config === null => throw new InvalidArgumentException("Gateway de pagamento desconhecido: {$name}"),
            $name === 'fake' => new FakeGateway((string) ($config['webhook_token'] ?? '')),
            default => throw new InvalidArgumentException("Gateway de pagamento sem adaptador: {$name}"),
        };
    }
}
