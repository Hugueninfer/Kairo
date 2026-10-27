<?php

namespace App\DTOs\Quotes;

use App\DTOs\Data;
use App\Enums\QuoteStatus;

final readonly class QuoteFilters extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?QuoteStatus $status = null,
        public ?string $client = null,
        public int $perPage = 20,
    ) {}
}
