<?php

namespace App\DTOs\Billing;

use App\DTOs\Data;

/**
 * Fatura montada à mão. Cliente e projeto chegam como ULID.
 */
final readonly class InvoiceData extends Data
{
    /**
     * @param  list<InvoiceItemData>  $items
     */
    public function __construct(
        public string $client,
        public string $due_date,
        public array $items,
        public ?string $project = null,
        public ?string $notes = null,
        public string $discount = '0.00',
    ) {}
}
