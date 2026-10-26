<?php

namespace App\Repositories\Eloquent;

use App\Criteria\ProjectFilterCriteria;
use App\Criteria\SearchCriteria;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectRepositoryEloquent extends BaseRepository implements ProjectRepository
{
    public function model(): string
    {
        return Project::class;
    }

    public function paginateFiltered(?string $search, ?ProjectStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Project> */
        return $this->filteredQuery([
            new SearchCriteria($search, ['name']),
            new ProjectFilterCriteria($status, $clientId),
        ])
            ->with('client:id,ulid,name')
            ->withCount($this->taskCounts())
            ->orderByRaw("case when status = 'active' then 0 when status = 'paused' then 1 else 2 end")
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function loadDetails(Project $project): Project
    {
        return $project->load('client:id,ulid,name')->loadCount($this->taskCounts());
    }

    public function createProject(array $attributes): Project
    {
        return $this->loadDetails(Project::query()->create($attributes));
    }

    public function updateProject(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $this->loadDetails($project);
    }

    public function deleteProject(Project $project): void
    {
        $project->delete();
    }

    /**
     * @return array<string, \Closure>
     */
    private function taskCounts(): array
    {
        return [
            'tasks as open_tasks_count' => fn ($query) => $query->where('status', '!=', TaskStatus::Done),
            'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
        ];
    }
}
