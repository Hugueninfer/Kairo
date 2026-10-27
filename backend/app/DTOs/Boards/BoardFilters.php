<?php

namespace App\DTOs\Boards;

use App\DTOs\Data;

final readonly class BoardFilters extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?string $project = null,
        public int $perPage = 24,
    ) {}
}
