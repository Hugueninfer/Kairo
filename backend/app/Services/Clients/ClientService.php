<?php

namespace App\Services\Clients;

use App\DTOs\Clients\ClientData;
use App\DTOs\Clients\ClientFilters;
use App\Models\Client;
use App\Repositories\Contracts\ClientRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientService
{
    public function __construct(private readonly ClientRepository $clients) {}

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function list(ClientFilters $filters): LengthAwarePaginator
    {
        return $this->clients->paginateFiltered($filters);
    }

    public function create(ClientData $data): Client
    {
        return $this->clients->createClient($data->toArray());
    }

    public function update(Client $client, ClientData $data): Client
    {
        return $this->clients->updateClient($client, $data->toArray());
    }

    /**
     * Os projetos do cliente continuam existindo, só ficam sem cliente.
     */
    public function delete(Client $client): void
    {
        $this->clients->deleteClient($client);
    }
}
