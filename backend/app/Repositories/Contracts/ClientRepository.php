<?php

namespace App\Repositories\Contracts;

use App\DTOs\Clients\ClientFilters;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Contracts\RepositoryInterface;

interface ClientRepository extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function paginateFiltered(ClientFilters $filters): LengthAwarePaginator;

    public function findByUlid(string $ulid): ?Client;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createClient(array $attributes): Client;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateClient(Client $client, array $attributes): Client;

    public function deleteClient(Client $client): void;
}
