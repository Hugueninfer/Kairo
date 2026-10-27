<?php

namespace App\Services\Quotes;

use App\DTOs\Quotes\ContractFilters;
use App\Enums\ContractStatus;
use App\Exceptions\Quotes\ContractNotActiveException;
use App\Models\Client;
use App\Models\Contract;
use App\Repositories\Contracts\ClientRepository;
use App\Repositories\Contracts\ContractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Contratos nascem do aceite do orçamento (QuoteService). Aqui só se consulta e encerra.
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
}
