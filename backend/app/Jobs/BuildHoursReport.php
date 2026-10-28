<?php

namespace App\Jobs;

use App\DTOs\Reports\HoursReportFilters;
use App\Services\Reports\ReportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Calcula o relatório de horas e guarda no cache. Pedidos iguais enquanto ele roda viram um só.
 */
class BuildHoursReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $companyId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $group,
        public readonly string $cacheKey,
    ) {}

    public function uniqueId(): string
    {
        return $this->cacheKey;
    }

    public function handle(ReportService $reports): void
    {
        /** @var 'project'|'member'|'client' $group */
        $group = $this->group;
        $reports->build($this->companyId, new HoursReportFilters($this->from, $this->to, $group), $this->cacheKey);
    }
}
