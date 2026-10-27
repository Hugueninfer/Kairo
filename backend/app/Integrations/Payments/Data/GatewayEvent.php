<?php

namespace App\Integrations\Payments\Data;

use App\Enums\PaymentMethod;
use Illuminate\Support\Carbon;

/**
 * Aviso do gateway já traduzido para o formato do Kairo. Eventos que não interessam
 * (cobrança criada, visualizada...) chegam com $confirmsPayment = false e são só registrados.
 *
 * @param  array<string, mixed>  $payload
 */
final readonly class GatewayEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $payload,
        public bool $confirmsPayment = false,
        public ?string $chargeId = null,
        public ?string $paymentId = null,
        public ?string $amount = null,
        public ?PaymentMethod $method = null,
        public ?Carbon $paidAt = null,
    ) {}
}
