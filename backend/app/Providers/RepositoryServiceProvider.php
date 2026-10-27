<?php

namespace App\Providers;

use App\Repositories\Contracts\ClientRepository;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\ContractRepository;
use App\Repositories\Contracts\InvitationRepository;
use App\Repositories\Contracts\PermissionRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\QuoteRepository;
use App\Repositories\Contracts\RoleRepository;
use App\Repositories\Contracts\TaskRepository;
use App\Repositories\Contracts\TimeEntryRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Eloquent\ClientRepositoryEloquent;
use App\Repositories\Eloquent\CompanyRepositoryEloquent;
use App\Repositories\Eloquent\ContractRepositoryEloquent;
use App\Repositories\Eloquent\InvitationRepositoryEloquent;
use App\Repositories\Eloquent\PermissionRepositoryEloquent;
use App\Repositories\Eloquent\ProjectRepositoryEloquent;
use App\Repositories\Eloquent\QuoteRepositoryEloquent;
use App\Repositories\Eloquent\RoleRepositoryEloquent;
use App\Repositories\Eloquent\TaskRepositoryEloquent;
use App\Repositories\Eloquent\TimeEntryRepositoryEloquent;
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
        CompanyRepository::class => CompanyRepositoryEloquent::class,
        InvitationRepository::class => InvitationRepositoryEloquent::class,
        RoleRepository::class => RoleRepositoryEloquent::class,
        PermissionRepository::class => PermissionRepositoryEloquent::class,
        ClientRepository::class => ClientRepositoryEloquent::class,
        ProjectRepository::class => ProjectRepositoryEloquent::class,
        TaskRepository::class => TaskRepositoryEloquent::class,
        TimeEntryRepository::class => TimeEntryRepositoryEloquent::class,
        QuoteRepository::class => QuoteRepositoryEloquent::class,
        ContractRepository::class => ContractRepositoryEloquent::class,
    ];
}
