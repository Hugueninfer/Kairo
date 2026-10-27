<?php

namespace App\DTOs\Billing;

use App\DTOs\Data;

final readonly class InvoiceItemData extends Data
{
    public function __construct(
        public string $description,
        public string $quantity,
        public string $unit_price,
    ) {}
}
