<?php

namespace App\Providers;

use App\Repositories\Contracts\UserRepository;
use App\Repositories\Eloquent\UserRepositoryEloquent;
use Illuminate\Support\ServiceProvider;

/**
 * Liga cada contrato de repository à sua implementação Eloquent.
 * Services dependem só das interfaces, então trocar a implementação não afeta a regra de negócio.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepository::class => UserRepositoryEloquent::class,
    ];
}
