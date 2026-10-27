<?php

namespace App\DTOs\Timesheet;

use App\DTOs\Data;

/**
 * Lançamento manual (ou edição): dia, hora de início opcional e duração, no fuso da empresa.
 * Projeto e tarefa vêm como ULID.
 */
final readonly class TimeEntryData extends Data
{
    public function __construct(
        public string $project,
        public string $date,
        public int $minutes,
        public ?string $task = null,
        public ?string $start = null,
        public ?string $description = null,
        public ?bool $billable = null,
    ) {}
}
