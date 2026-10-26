<?php

namespace App\DTOs\Tasks;

use App\DTOs\Data;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

final readonly class TaskData extends Data
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public TaskStatus $status = TaskStatus::Todo,
        public TaskPriority $priority = TaskPriority::Medium,
        public ?int $assignee_id = null,
        public ?int $estimate_minutes = null,
        public ?string $due_on = null,
    ) {}
}
