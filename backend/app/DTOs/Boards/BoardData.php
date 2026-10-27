<?php

namespace App\DTOs\Boards;

use App\DTOs\Data;

final readonly class BoardData extends Data
{
    public function __construct(
        public string $title,
        public ?string $project = null,
    ) {}
}
