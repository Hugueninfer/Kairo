<?php

namespace App\Integrations\Payments\Data;

/**
 * Quem paga, como o gateway precisa para emitir Pix e boleto.
 */
final readonly class Customer
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $document = null,
        public ?string $phone = null,
    ) {}
}
