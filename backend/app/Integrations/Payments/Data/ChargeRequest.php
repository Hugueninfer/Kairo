<?php

namespace App\Integrations\Payments\Data;

/**
 * Pedido de cobrança enviado ao gateway. O valor vai como texto decimal ("1500.00").
 */
final readonly class ChargeRequest
{
    public function __construct(
        public string $reference,
        public string $description,
        public string $amount,
        public string $dueDate,
        public Customer $customer,
    ) {}
}
