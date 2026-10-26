<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Prettus\Repository\Contracts\RepositoryInterface;

interface UserRepository extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findOrFail(int $id): User;

    public function updatePassword(User $user, string $password): void;
}
