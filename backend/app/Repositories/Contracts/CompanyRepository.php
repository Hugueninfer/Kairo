<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Prettus\Repository\Contracts\RepositoryInterface;

interface CompanyRepository extends RepositoryInterface
{
    /**
     * @return Collection<int, Company>
     */
    public function forUser(User $user): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWithOwner(array $attributes, User $owner): Company;

    public function findByUlid(string $ulid): ?Company;

    public function isMember(Company $company, User $user): bool;

    public function findMember(Company $company, int $userId): ?User;

    public function addMember(Company $company, User $user): void;

    public function removeMember(Company $company, User $user): void;

    /**
     * @return Collection<int, User>
     */
    public function members(Company $company): Collection;
}
