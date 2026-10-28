<?php

namespace App\DTOs\Reports;

use App\DTOs\Data;

final readonly class HoursReportFilters extends Data
{
    /**
     * @param  'project'|'member'|'client'  $group
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $group = 'project',
    ) {}
}
