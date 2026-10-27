<?php

namespace App\Services\Quotes;

use App\DTOs\Quotes\ContractFilters;
use App\DTOs\Quotes\RecurrenceData;
use App\Enums\ContractStatus;
use App\Exceptions\Quotes\ContractNotActiveException;
use App\Models\Client;
use App\Models\Contract;
use App\Repositories\Contracts\ClientRepository;
use App\Repositories\Contracts\ContractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Contratos nascem do aceite do orçamento (QuoteService). Aqui se consulta, configura a
 * cobrança recorrente e encerra.
 */
class ContractService
{
    public function __construct(
        private readonly ContractRepository $contracts,
        private readonly ClientRepository $clients,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Contract>
     */
    public function list(ContractFilters $filters): LengthAwarePaginator
    {
        $clientId = null;

        if ($filters->client !== null) {
            $client = $this->clients->findByUlid($filters->client) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$filters->client]);
            $clientId = $client->id;
        }

        return $this->contracts->paginateFiltered($filters->search, $filters->status, $clientId, $filters->perPage);
    }

    public function show(Contract $contract): Contract
    {
        return $this->contracts->loadDetails($contract);
    }

    /**
     * Concluir ou cancelar um contrato vigente.
     */
    public function close(Contract $contract, ContractStatus $status): Contract
    {
        if ($contract->status !== ContractStatus::Active || $status === ContractStatus::Active) {
            throw new ContractNotActiveException;
        }

        return $this->contracts->changeStatus($contract, $status);
    }

    /**
     * Liga, altera ou desliga a cobrança recorrente. A primeira fatura sai na data de início.
     */
    public function configureRecurrence(Contract $contract, RecurrenceData $data): Contract
    {
        if ($contract->status !== ContractStatus::Active) {
            throw new ContractNotActiveException;
        }

        if ($data->frequency === null) {
            return $this->contracts->updateContract($contract, [
                'billing_frequency' => null,
                'recurring_amount' => null,
                'next_billing_on' => null,
                'billing_ends_on' => null,
            ]);
        }

        return $this->contracts->updateContract($contract, [
            'billing_frequency' => $data->frequency,
            'recurring_amount' => $data->amount,
            'next_billing_on' => $data->starts_on,
            'billing_ends_on' => $data->ends_on,
            'payment_term_days' => $data->payment_term_days,
            'auto_send' => $data->auto_send,
        ]);
    }
}
