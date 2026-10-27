<?php

namespace App\Repositories\Contracts;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Contracts\RepositoryInterface;

interface InvoiceRepository extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateFiltered(?string $search, ?InvoiceStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator;

    public function findByUlid(string $ulid): ?Invoice;

    /**
     * Busca sem depender da empresa ativa (fila e webhooks), com empresa, cliente, itens e quem criou.
     */
    public function findForProcessing(string $ulid): ?Invoice;

    /**
     * Fatura da cobrança avisada pelo gateway, sem depender da empresa ativa.
     */
    public function findByCharge(string $gateway, string $chargeId): ?Invoice;

    /**
     * Carrega itens, cliente, projeto, contrato e pagamentos.
     */
    public function loadDetails(Invoice $invoice): Invoice;

    /**
     * Relê a fatura travando a linha, para dois avisos de pagamento simultâneos não se atropelarem.
     */
    public function lockForUpdate(Invoice $invoice): Invoice;

    /**
     * Próximo número da empresa no ano: FAT-2026-0001. Precisa rodar numa transação.
     */
    public function nextNumber(int $companyId, int $year): string;

    /**
     * Cria a fatura com os itens e prende a ela os apontamentos de horas informados.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     * @param  list<int>  $timeEntryIds
     */
    public function createInvoice(array $attributes, array $items, array $timeEntryIds = []): Invoice;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $items
     */
    public function updateInvoice(Invoice $invoice, array $attributes, ?array $items = null): Invoice;

    /**
     * Solta os apontamentos de horas presos à fatura, para poderem ser faturados de novo.
     */
    public function releaseTimeEntries(Invoice $invoice): void;

    public function deleteInvoice(Invoice $invoice): void;

    /**
     * Marca como vencidas as faturas em aberto com vencimento antes de $today. Devolve quantas.
     */
    public function markOverdue(string $today): int;

    /**
     * Totais da empresa ativa: a receber, vencido e recebido no mês.
     *
     * @return array{receivable: string, overdue: string, received_this_month: string}
     */
    public function summary(string $monthStart): array;
}
