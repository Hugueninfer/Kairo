<?php

namespace App\DTOs\Projects;

use App\DTOs\Data;
use App\Enums\ProjectStatus;

final readonly class ProjectFilters extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?ProjectStatus $status = null,
        public ?string $client = null,
        public int $perPage = 20,
    ) {}
}
