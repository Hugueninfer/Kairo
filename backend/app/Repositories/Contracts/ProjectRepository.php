<?php

namespace App\Repositories\Contracts;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Contracts\RepositoryInterface;

interface ProjectRepository extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateFiltered(?string $search, ?ProjectStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator;

    /**
     * Carrega cliente e a contagem de tarefas abertas e concluídas.
     */
    public function loadDetails(Project $project): Project;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createProject(array $attributes): Project;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProject(Project $project, array $attributes): Project;

    public function deleteProject(Project $project): void;
}
