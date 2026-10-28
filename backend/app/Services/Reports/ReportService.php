<?php

namespace App\Services\Reports;

use App\DTOs\Reports\HoursReportFilters;
use App\Jobs\BuildHoursReport;
use App\Models\Company;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Support\TenantContext;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * Relatório de horas: calculado na fila e guardado em cache até alguma hora da empresa mudar.
 */
class ReportService
{
    /** O relatório pronto fica no cache por até um dia (some antes se as horas mudarem). */
    public const CACHE_SECONDS = 86400;

    public function __construct(
        private readonly ReportRepository $reports,
        private readonly CompanyRepository $companies,
        private readonly TenantContext $tenant,
        private readonly Cache $cache,
    ) {}

    /**
     * Devolve o relatório pronto, ou pede o cálculo na fila e avisa que está processando.
     *
     * @return array{status: 'ready'|'processing', data: array<string, mixed>|null}
     */
    public function hours(Company $company, HoursReportFilters $filters): array
    {
        $key = $this->key($company, $filters);
        $ready = $this->cache->get($key);

        if (! is_array($ready)) {
            BuildHoursReport::dispatch($company->id, $filters->from, $filters->to, $filters->group, $key);
            // Com a fila síncrona (testes, desenvolvimento) o relatório já ficou pronto.
            $ready = $this->cache->get($key);
        }

        return is_array($ready) ? ['status' => 'ready', 'data' => $ready] : ['status' => 'processing', 'data' => null];
    }

    /**
     * Calcula e guarda o relatório (roda na fila).
     */
    public function build(int $companyId, HoursReportFilters $filters, string $key): void
    {
        $company = $this->companies->find($companyId);

        if (! $company instanceof Company) {
            return;
        }

        $this->tenant->set($company);
        [$from, $to] = $this->period($company, $filters);
        $rows = $this->reports->hoursBy($filters->group, $from, $to);

        $this->cache->put($key, [
            'from' => $filters->from,
            'to' => $filters->to,
            'group' => $filters->group,
            'generated_at' => now()->toIso8601String(),
            'rows' => $rows,
            'totals' => [
                'minutes' => array_sum(array_column($rows, 'minutes')),
                'billable_minutes' => array_sum(array_column($rows, 'billable_minutes')),
                'amount' => number_format(array_sum(array_map(fn (array $row) => (float) $row['amount'], $rows)), 2, '.', ''),
            ],
        ], self::CACHE_SECONDS);
    }

    /**
     * A chave muda quando qualquer hora da empresa muda: o relatório antigo deixa de ser usado sozinho.
     */
    private function key(Company $company, HoursReportFilters $filters): string
    {
        return sprintf(
            'reports:hours:%d:%s:%s:%s:%s',
            $company->id,
            $filters->group,
            $filters->from,
            $filters->to,
            hash('sha256', $this->reports->hoursFingerprint()),
        );
    }

    /**
     * Dias do filtro no fuso da empresa, convertidos para UTC (fim exclusivo).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Company $company, HoursReportFilters $filters): array
    {
        return [
            Carbon::parse($filters->from, $company->timezone)->startOfDay()->utc(),
            Carbon::parse($filters->to, $company->timezone)->addDay()->startOfDay()->utc(),
        ];
    }
}
