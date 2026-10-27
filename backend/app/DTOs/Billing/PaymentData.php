<?php

namespace App\DTOs\Billing;

use App\DTOs\Data;
use App\Enums\PaymentMethod;

/**
 * Pagamento registrado à mão (dinheiro, transferência, Pix fora do gateway).
 */
final readonly class PaymentData extends Data
{
    public function __construct(
        public string $amount,
        public PaymentMethod $method,
        public string $paid_on,
    ) {}
}
