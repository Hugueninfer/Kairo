<?php

namespace Tests\Fixtures;

use App\DTOs\Data;

final readonly class ExampleData extends Data
{
    public function __construct(
        public string $name,
        public ExampleStatus $status,
    ) {}
}
