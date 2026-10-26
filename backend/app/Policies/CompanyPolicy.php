<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Por enquanto só o dono administra a empresa. Na fase de papéis e permissões,
 * estas regras passam a consultar as permissões do papel do usuário.
 */
class CompanyPolicy
{
    public function update(User $user, Company $company): bool
    {
        return $company->isOwnedBy($user);
    }

    public function manageMembers(User $user, Company $company): bool
    {
        return $company->isOwnedBy($user);
    }
}
