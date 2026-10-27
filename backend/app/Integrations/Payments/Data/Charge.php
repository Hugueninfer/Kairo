<?php

namespace App\Integrations\Payments\Data;

/**
 * Cobrança criada no gateway: o id para conciliar os avisos e o link que vai para o cliente.
 */
final readonly class Charge
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
