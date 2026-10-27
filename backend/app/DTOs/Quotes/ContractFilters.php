<?php

namespace App\DTOs\Quotes;

use App\DTOs\Data;
use App\Enums\ContractStatus;

final readonly class ContractFilters extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?ContractStatus $status = null,
        public ?string $client = null,
        public int $perPage = 20,
    ) {}
}
