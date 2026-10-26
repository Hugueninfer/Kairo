<?php

namespace App\Repositories\Eloquent;

use App\Criteria\SearchCriteria;
use App\DTOs\Clients\ClientFilters;
use App\Models\Client;
use App\Repositories\Contracts\ClientRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepositoryEloquent extends BaseRepository implements ClientRepository
{
    public function model(): string
    {
        return Client::class;
    }

    public function paginateFiltered(ClientFilters $filters): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Client> */
        return $this->filteredQuery([new SearchCriteria($filters->search, ['name', 'email', 'document'])])
            ->withCount('projects')
            ->orderBy('name')
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    public function findByUlid(string $ulid): ?Client
    {
        /** @var Client|null */
        return $this->filteredQuery()->where('ulid', $ulid)->first();
    }

    public function createClient(array $attributes): Client
    {
        return Client::query()->create($attributes);
    }

    public function updateClient(Client $client, array $attributes): Client
    {
        $client->update($attributes);

        return $client;
    }

    public function deleteClient(Client $client): void
    {
        $client->delete();
    }
}
