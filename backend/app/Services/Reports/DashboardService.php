<?php

namespace App\Services\Reports;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\ReportRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * Números do painel inicial. Cada bloco só aparece para quem tem a permissão dele.
 */
class DashboardService
{
    /** Por quanto tempo o painel fica em cache (segundos); qualquer mudança nos dados troca a chave antes disso. */
    public const CACHE_SECONDS = 60;

    /** A partir de quanto do orçamento consumido o projeto aparece no alerta. */
    public const BUDGET_ALERT_RATIO = 0.8;

    private const MONTHS_OF_REVENUE = 6;

    public function __construct(
        private readonly ReportRepository $reports,
        private readonly Cache $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(User $user, Company $company): array
    {
        $finance = $user->can(Permission::InvoicesView->value);
        $team = $user->can(Permission::TimesheetViewAll->value);
        $projects = $user->can(Permission::ProjectsView->value);
        // A impressão digital no nome do cache faz o painel refletir na hora um pagamento ou hora lançada.
        $key = sprintf(
            'dashboard:%d:%d:%d%d%d:%s',
            $company->id,
            $user->id,
            $finance,
            $team,
            $projects,
            hash('sha256', $this->reports->dashboardFingerprint()),
        );

        return $this->cache->remember($key, self::CACHE_SECONDS, function () use ($user, $company, $finance, $team, $projects): array {
            $now = now($company->timezone);
            $monthStart = $now->copy()->startOfMonth();
            $nextMonth = $monthStart->copy()->addMonth();

            return [
                'month' => ucfirst($monthStart->locale('pt_BR')->translatedFormat('F \d\e Y')),
                'hours' => [
                    'mine' => $this->reports->minutesBetween($monthStart->copy()->utc(), $nextMonth->copy()->utc(), $user->id),
                    'team' => $team ? $this->reports->minutesBetween($monthStart->copy()->utc(), $nextMonth->copy()->utc()) : null,
                ],
                'finance' => $finance ? $this->finance($monthStart) : null,
                'projects_over_budget' => $projects
                    ? $this->reports->projectsOverBudget(self::BUDGET_ALERT_RATIO, (string) ($company->default_hourly_rate ?? '0'))
                    : null,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function finance(Carbon $monthStart): array
    {
        $first = $monthStart->copy()->subMonths(self::MONTHS_OF_REVENUE - 1);
        $months = [];

        for ($month = $first->copy(); $month->lte($monthStart); $month->addMonth()) {
            $months[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'label' => $month->locale('pt_BR')->translatedFormat('M/y'),
                'cents' => 0,
            ];
        }

        // Soma por mês no fuso da empresa (um pagamento às 23h do dia 31 é do mês 31, não do seguinte).
        foreach ($this->reports->paymentsSince($first->copy()->utc()) as $payment) {
            $key = $payment['paid_at']->copy()->setTimezone($monthStart->getTimezone())->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['cents'] += (int) round((float) $payment['amount'] * 100);
            }
        }

        $revenue = array_map(fn (array $month) => [
            'month' => $month['month'],
            'label' => $month['label'],
            'total' => number_format($month['cents'] / 100, 2, '.', ''),
        ], array_values($months));

        return [
            ...$this->reports->receivables(),
            'received_this_month' => $revenue[array_key_last($revenue)]['total'],
            'revenue' => $revenue,
        ];
    }
}
