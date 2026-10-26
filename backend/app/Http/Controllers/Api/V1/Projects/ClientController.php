<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Requests\Clients\ListClientsRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\Clients\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clients) {}

    public function index(ListClientsRequest $request): AnonymousResourceCollection
    {
        return ClientResource::collection($this->clients->list($request->toFilters()));
    }

    public function show(Client $client): ClientResource
    {
        return ClientResource::make($client);
    }

    public function store(ClientRequest $request): JsonResponse
    {
        return ClientResource::make($this->clients->create($request->toData()))->response()->setStatusCode(201);
    }

    public function update(ClientRequest $request, Client $client): ClientResource
    {
        return ClientResource::make($this->clients->update($client, $request->toData()));
    }

    public function destroy(Client $client): Response
    {
        $this->clients->delete($client);

        return response()->noContent();
    }
}
