<?php

namespace App\DTOs\Quotes;

use App\DTOs\Data;
use App\Enums\QuoteItemType;

/**
 * Quantidade e preço chegam como texto decimal ("1.50"), para não perder centavos em float.
 */
final readonly class QuoteItemData extends Data
{
    public function __construct(
        public string $description,
        public QuoteItemType $type,
        public string $quantity,
        public string $unit_price,
    ) {}
}
