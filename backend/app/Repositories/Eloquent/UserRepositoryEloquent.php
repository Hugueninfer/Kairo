<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepository;

class UserRepositoryEloquent extends BaseRepository implements UserRepository
{
    /**
     * @var array<string, string>
     */
    protected $fieldSearchable = [
        'name' => 'like',
        'email' => '=',
    ];

    public function model(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        $this->applyCriteria();
        $this->applyScope();

        /** @var User|null $user */
        $user = $this->model->where('email', $email)->first();

        $this->resetModel();

        return $user;
    }
}
