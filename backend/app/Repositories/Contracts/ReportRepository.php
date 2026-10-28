<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Carbon;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Consultas agregadas do dashboard e dos relatórios, sempre na empresa ativa.
 */
interface ReportRepository extends RepositoryInterface
{
    /**
     * Minutos lançados no período (horas recusadas e cronômetros rodando ficam de fora).
     *
     * @return array{total: int, billable: int}
     */
    public function minutesBetween(Carbon $from, Carbon $to, ?int $userId = null): array;

    /**
     * Horas do período agrupadas por projeto, pessoa ou cliente, das que têm mais horas para as que têm menos.
     *
     * @return list<array{key: string, label: string, minutes: int, billable_minutes: int, amount: string}>
     */
    public function hoursBy(string $group, Carbon $from, Carbon $to): array;

    /**
     * Projetos ativos com orçamento que já consumiram pelo menos $ratio dele (horas × valor/hora).
     *
     * @return list<array{id: string, name: string, budget: string, consumed: string, percent: int}>
     */
    public function projectsOverBudget(float $ratio, string $defaultRate): array;

    /**
     * Valores recebidos desde $from, para somar por mês no fuso da empresa.
     *
     * @return list<array{paid_at: Carbon, amount: string}>
     */
    public function paymentsSince(Carbon $from): array;

    /**
     * Quantidade e saldo das faturas em aberto e das vencidas.
     *
     * @return array{open_count: int, open_total: string, overdue_count: int, overdue_total: string}
     */
    public function receivables(): array;

    /**
     * Muda sempre que alguma hora da empresa é criada, alterada ou apagada (para invalidar relatórios em cache).
     */
    public function hoursFingerprint(): string;

    /**
     * Muda sempre que horas, faturas, pagamentos ou projetos da empresa mudam (para invalidar o painel em cache).
     */
    public function dashboardFingerprint(): string;
}
