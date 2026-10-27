<?php

namespace App\DTOs\Quotes;

use App\DTOs\Data;

/**
 * O cliente vem como ULID; o Service troca pelo id interno.
 */
final readonly class QuoteData extends Data
{
    /**
     * @param  list<QuoteItemData>  $items
     */
    public function __construct(
        public string $client,
        public string $title,
        public string $valid_until,
        public array $items,
        public ?string $introduction = null,
        public ?string $terms = null,
        public string $discount = '0.00',
        public string $tax_rate = '0.00',
    ) {}
}
