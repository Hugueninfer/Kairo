<?php

namespace App\Services\Companies;

use App\DTOs\Companies\CompanyData;
use App\Exceptions\Companies\NotCompanyMemberException;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\UserRepository;
use App\Support\Transactions\TransactionManager;
use Illuminate\Database\Eloquent\Collection;

class CompanyService
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly UserRepository $users,
        private readonly RoleService $roles,
        private readonly TransactionManager $transaction,
    ) {}

    /**
     * @return Collection<int, Company>
     */
    public function listForUser(User $user): Collection
    {
        return $this->companies->forUser($user);
    }

    /**
     * Cria a empresa com o usuário como dono, monta os papéis padrão e já a torna
     * a empresa ativa dele.
     */
    public function create(User $owner, CompanyData $data): Company
    {
        return $this->transaction->run(function () use ($owner, $data): Company {
            $company = $this->companies->createWithOwner($data->toArray(), $owner);
            $this->roles->setupCompany($company, $owner);
            $this->users->setCurrentCompany($owner, $company);

            return $company;
        });
    }

    public function update(Company $company, CompanyData $data): Company
    {
        /** @var Company */
        return $this->companies->update($data->toArray(), $company->id);
    }

    public function switchTo(User $user, Company $company): Company
    {
        if (! $this->companies->isMember($company, $user)) {
            throw new NotCompanyMemberException;
        }

        $this->users->setCurrentCompany($user, $company);

        return $company;
    }
}
