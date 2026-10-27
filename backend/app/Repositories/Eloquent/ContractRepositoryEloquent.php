<?php

namespace App\Repositories\Eloquent;

use App\Criteria\DocumentFilterCriteria;
use App\Criteria\SearchCriteria;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Repositories\Contracts\ContractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ContractRepositoryEloquent extends BaseRepository implements ContractRepository
{
    public function model(): string
    {
        return Contract::class;
    }

    public function paginateFiltered(?string $search, ?ContractStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Contract> */
        return $this->filteredQuery([
            new SearchCriteria($search, ['title', 'number']),
            new DocumentFilterCriteria($status, $clientId),
        ])
            ->with(['client:id,ulid,name,deleted_at', 'project:id,ulid,name', 'quote:id,ulid,number'])
            ->latest('signed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUlid(string $ulid): ?Contract
    {
        /** @var Contract|null */
        return $this->filteredQuery()->where('ulid', $ulid)->first();
    }

    public function loadDetails(Contract $contract): Contract
    {
        return $contract->load(['client:id,ulid,name,deleted_at', 'project:id,ulid,name', 'quote:id,ulid,number']);
    }

    public function nextNumber(int $companyId, int $year): string
    {
        return $this->nextSequence($companyId, "CTR-{$year}-");
    }

    public function createContract(array $attributes): Contract
    {
        return Contract::query()->create($attributes);
    }

    public function changeStatus(Contract $contract, ContractStatus $status): Contract
    {
        $contract->update(['status' => $status]);

        return $this->loadDetails($contract);
    }

    public function updateContract(Contract $contract, array $attributes): Contract
    {
        $contract->update($attributes);

        return $this->loadDetails($contract);
    }

    public function dueForBilling(string $date): Collection
    {
        /** @var Collection<int, int> */
        return Contract::query()
            ->withoutGlobalScope('company')
            ->where('status', ContractStatus::Active)
            ->whereNotNull('billing_frequency')
            ->whereDate('next_billing_on', '<=', $date)
            ->orderBy('id')
            ->pluck('id');
    }

    public function lockForBilling(int $id): ?Contract
    {
        return Contract::query()
            ->withoutGlobalScope('company')
            ->with(['company.owner', 'client'])
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }
}
