<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use App\Models\User;
use Prettus\Repository\Contracts\RepositoryInterface;

interface UserRepository extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findOrFail(int $id): User;

    public function updatePassword(User $user, string $password): void;

    public function setCurrentCompany(User $user, ?Company $company): void;

    /**
     * Guarda um segredo novo, ainda não confirmado (desliga o que existia). Null apaga tudo.
     */
    public function setTwoFactorSecret(User $user, ?string $secret): void;

    /**
     * @param  list<string>  $hashedRecoveryCodes
     */
    public function confirmTwoFactor(User $user, array $hashedRecoveryCodes): void;

    /**
     * @param  list<string>  $hashedRecoveryCodes
     */
    public function setRecoveryCodes(User $user, array $hashedRecoveryCodes): void;

    public function setTwoFactorWindow(User $user, int $window): void;
}
