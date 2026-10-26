<?php

namespace App\Services\Projects;

use App\DTOs\Projects\ProjectData;
use App\DTOs\Projects\ProjectFilters;
use App\Models\Client;
use App\Models\Project;
use App\Repositories\Contracts\ClientRepository;
use App\Repositories\Contracts\ProjectRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ClientRepository $clients,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function list(ProjectFilters $filters): LengthAwarePaginator
    {
        return $this->projects->paginateFiltered(
            $filters->search,
            $filters->status,
            $filters->client === null ? null : $this->clientId($filters->client),
            $filters->perPage,
        );
    }

    public function show(Project $project): Project
    {
        return $this->projects->loadDetails($project);
    }

    public function create(ProjectData $data): Project
    {
        return $this->projects->createProject($this->attributes($data));
    }

    public function update(Project $project, ProjectData $data): Project
    {
        return $this->projects->updateProject($project, $this->attributes($data));
    }

    public function delete(Project $project): void
    {
        $this->projects->deleteProject($project);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ProjectData $data): array
    {
        $attributes = $data->toArray();
        unset($attributes['client']);
        $attributes['client_id'] = $data->client === null ? null : $this->clientId($data->client);

        return $attributes;
    }

    private function clientId(string $ulid): int
    {
        $client = $this->clients->findByUlid($ulid) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$ulid]);

        return $client->id;
    }
}
