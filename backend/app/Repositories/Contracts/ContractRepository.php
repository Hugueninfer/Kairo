<?php

namespace App\Repositories\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Contracts\RepositoryInterface;

interface ContractRepository extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Contract>
     */
    public function paginateFiltered(?string $search, ?ContractStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator;

    public function findByUlid(string $ulid): ?Contract;

    public function loadDetails(Contract $contract): Contract;

    /**
     * Próximo número da empresa no ano: CTR-2026-0001. Precisa rodar numa transação.
     */
    public function nextNumber(int $companyId, int $year): string;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createContract(array $attributes): Contract;

    public function changeStatus(Contract $contract, ContractStatus $status): Contract;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateContract(Contract $contract, array $attributes): Contract;

    /**
     * Ids dos contratos vigentes, de todas as empresas, com cobrança recorrente marcada até $date.
     *
     * @return Collection<int, int>
     */
    public function dueForBilling(string $date): Collection;

    /**
     * Relê o contrato travando a linha (sem depender da empresa ativa), com empresa e cliente.
     * Precisa rodar numa transação.
     */
    public function lockForBilling(int $id): ?Contract;
}
