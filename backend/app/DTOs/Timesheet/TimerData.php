<?php

namespace App\DTOs\Timesheet;

use App\DTOs\Data;

final readonly class TimerData extends Data
{
    public function __construct(
        public string $project,
        public ?string $task = null,
        public ?string $description = null,
    ) {}
}
