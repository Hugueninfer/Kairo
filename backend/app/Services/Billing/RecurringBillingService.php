<?php

namespace App\Services\Billing;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Repositories\Contracts\ContractRepository;
use App\Repositories\Contracts\InvoiceRepository;
use App\Support\TenantContext;
use App\Support\Transactions\TransactionManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gera as faturas dos contratos recorrentes quando chega a data de cobrança e,
 * se o contrato pede, já envia ao cliente com o link de pagamento.
 */
class RecurringBillingService
{
    /** Teto de períodos atrasados gerados de uma vez (ex.: o Scheduler ficou parado). */
    private const MAX_CATCH_UP = 12;

    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceService $invoiceService,
        private readonly TenantContext $tenant,
        private readonly TransactionManager $transaction,
    ) {}

    /**
     * Contratos com cobrança até amanhã em UTC: cobre as empresas em fusos à frente.
     * A data exata de cada um é conferida no fuso da empresa em bill().
     *
     * @return Collection<int, int>
     */
    public function dueContracts(): Collection
    {
        return $this->contracts->dueForBilling(now()->addDay()->toDateString());
    }

    /**
     * Gera as faturas que venceram para o contrato. Devolve quantas foram criadas.
     */
    public function bill(int $contractId): int
    {
        /** @var list<Invoice> $created */
        [$created, $send] = $this->transaction->run(function () use ($contractId): array {
            $contract = $this->contracts->lockForBilling($contractId);

            if ($contract === null || ! $this->isRecurring($contract)) {
                return [[], false];
            }

            $this->tenant->set($contract->company);
            $today = now($contract->company->timezone)->startOfDay();
            /** @var Carbon $next */
            $next = $contract->next_billing_on;
            $created = [];

            for ($i = 0; $i < self::MAX_CATCH_UP && $next !== null && $next->lte($today); $i++) {
                if (! $this->invoices->existsForPeriod($contract->id, $next->toDateString())) {
                    $created[] = $this->invoiceService->createRecurring($contract, $next);
                }

                $next = $contract->billing_frequency?->next($next);

                if ($next !== null && $contract->billing_ends_on !== null && $next->gt($contract->billing_ends_on)) {
                    $next = null;
                }
            }

            $send = $contract->auto_send && filled($contract->client->email);
            $this->contracts->updateContract($contract, ['next_billing_on' => $next?->toDateString()]);

            return [$created, $send];
        });

        if ($send) {
            foreach ($created as $invoice) {
                $this->invoiceService->send($invoice);
            }
        }

        return count($created);
    }

    private function isRecurring(Contract $contract): bool
    {
        return $contract->status === ContractStatus::Active
            && $contract->billing_frequency !== null
            && $contract->recurring_amount !== null
            && $contract->next_billing_on !== null;
    }
}
