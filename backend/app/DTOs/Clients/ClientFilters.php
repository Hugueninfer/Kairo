<?php

namespace App\DTOs\Clients;

use App\DTOs\Data;

final readonly class ClientFilters extends Data
{
    public function __construct(
        public ?string $search = null,
        public int $perPage = 20,
    ) {}
}
