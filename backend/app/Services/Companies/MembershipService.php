<?php

namespace App\Services\Companies;

use App\Exceptions\Companies\CannotRemoveOwnerException;
use App\Exceptions\Companies\NotCompanyMemberException;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Database\Eloquent\Collection;

class MembershipService
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly UserRepository $users,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function members(Company $company): Collection
    {
        return $this->companies->members($company);
    }

    public function remove(Company $company, User $member): void
    {
        if ($company->isOwnedBy($member)) {
            throw new CannotRemoveOwnerException;
        }

        if (! $this->companies->isMember($company, $member)) {
            throw new NotCompanyMemberException;
        }

        $this->companies->removeMember($company, $member);

        if ($member->current_company_id === $company->id) {
            $this->users->setCurrentCompany($member, null);
        }
    }
}
