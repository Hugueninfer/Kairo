<?php

namespace App\Repositories\Eloquent;

use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CompanyRepositoryEloquent extends BaseRepository implements CompanyRepository
{
    public function model(): string
    {
        return Company::class;
    }

    public function forUser(User $user): Collection
    {
        return $user->companies()->orderBy('name')->get();
    }

    public function createWithOwner(array $attributes, User $owner): Company
    {
        return DB::transaction(function () use ($attributes, $owner): Company {
            $company = new Company($attributes);
            $company->owner()->associate($owner);
            $company->save();

            $company->members()->attach($owner->id);

            return $company;
        });
    }

    public function findByUlid(string $ulid): ?Company
    {
        /** @var Company|null */
        return Company::query()->where('ulid', $ulid)->first();
    }

    public function isMember(Company $company, User $user): bool
    {
        return $company->members()->whereKey($user->id)->exists();
    }

    public function findMember(Company $company, int $userId): ?User
    {
        /** @var User|null */
        return $company->members()->whereKey($userId)->first();
    }

    public function addMember(Company $company, User $user): void
    {
        $company->members()->syncWithoutDetaching([$user->id]);
    }

    public function removeMember(Company $company, User $user): void
    {
        $company->members()->detach($user->id);
    }

    public function members(Company $company): Collection
    {
        // Os papéis vêm do time atual do spatie (a empresa ativa).
        return $company->members()->with('roles')->orderBy('name')->get();
    }

    public function withRemindersEnabled(): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, int> */
        return Company::query()->where('reminders_enabled', true)->orderBy('id')->pluck('id');
    }
}
