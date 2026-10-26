<?php

namespace App\DTOs\Timesheet;

use App\DTOs\Data;

/**
 * Período em datas (Y-m-d, fuso da empresa), inclusive nas duas pontas.
 */
final readonly class TimeEntryFilters extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public ?int $userId = null,
        public ?string $project = null,
    ) {}
}
