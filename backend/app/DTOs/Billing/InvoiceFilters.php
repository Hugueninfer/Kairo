<?php

namespace App\DTOs\Billing;

use App\DTOs\Data;
use App\Enums\InvoiceStatus;

final readonly class InvoiceFilters extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?InvoiceStatus $status = null,
        public ?string $client = null,
        public int $perPage = 20,
    ) {}
}
