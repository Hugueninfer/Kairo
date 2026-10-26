<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Prettus\Repository\Contracts\RepositoryInterface;

interface InvitationRepository extends RepositoryInterface
{
    public function createPending(Company $company, User $inviter, string $email, string $tokenHash, ?Role $role = null): Invitation;

    /**
     * Busca por token sem filtro de empresa: quem aceita ainda não está na empresa.
     */
    public function findPendingByTokenHash(string $tokenHash): ?Invitation;

    /**
     * @return Collection<int, Invitation>
     */
    public function pendingForCompany(): Collection;

    public function revokePendingForEmail(string $email): void;

    public function markAccepted(Invitation $invitation): void;

    public function revoke(Invitation $invitation): void;

    /**
     * Marca como expirados os convites pendentes vencidos. Retorna quantos mudaram.
     */
    public function expireOverdue(): int;
}
