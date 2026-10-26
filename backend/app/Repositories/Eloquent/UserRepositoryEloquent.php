<?php

namespace App\Repositories\Eloquent;

use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Support\Str;

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
        $user = $this->model->where('email', mb_strtolower(trim($email)))->first();

        $this->resetModel();

        return $user;
    }

    public function findOrFail(int $id): User
    {
        $this->applyCriteria();
        $this->applyScope();

        /** @var User $user */
        $user = $this->model->findOrFail($id);

        $this->resetModel();

        return $user;
    }

    public function updatePassword(User $user, string $password): void
    {
        // remember_token não é fillable, então usamos forceFill de forma explícita e restrita a este caso.
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function setCurrentCompany(User $user, ?Company $company): void
    {
        $user->forceFill(['current_company_id' => $company?->id])->save();
        $user->setRelation('currentCompany', $company);
    }
}
