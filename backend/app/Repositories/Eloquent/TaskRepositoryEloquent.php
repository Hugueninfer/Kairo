<?php

namespace App\Repositories\Eloquent;

use App\Criteria\TaskFilterCriteria;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskRepositoryEloquent extends BaseRepository implements TaskRepository
{
    public function model(): string
    {
        return Task::class;
    }

    public function forProject(Project $project): Collection
    {
        /** @var Collection<int, Task> */
        return $this->filteredQuery([new TaskFilterCriteria(projectId: $project->id)])
            ->with('assignee:id,name')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function openAssignedTo(User $user): Collection
    {
        /** @var Collection<int, Task> */
        return $this->filteredQuery([new TaskFilterCriteria(assigneeId: $user->id, open: true)])
            ->with(['project:id,ulid,name', 'assignee:id,name'])
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();
    }

    public function nextPosition(Project $project, TaskStatus $status): int
    {
        $max = $this->column($project->id, $status)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    public function createTask(array $attributes): Task
    {
        return Task::query()->create($attributes)->load('assignee:id,name');
    }

    public function updateTask(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $task->load('assignee:id,name');
    }

    public function move(Task $task, TaskStatus $status, int $position, array $attributes = []): Task
    {
        return DB::transaction(function () use ($task, $status, $position, $attributes): Task {
            // Fecha o espaço que a tarefa deixa na coluna de origem.
            $this->column($task->project_id, $task->status)
                ->whereKeyNot($task->id)
                ->where('position', '>', $task->position)
                ->decrement('position');

            $others = $this->column($task->project_id, $status)->whereKeyNot($task->id);
            $position = max(0, min($position, (clone $others)->count()));

            // Abre espaço na coluna de destino.
            $others->where('position', '>=', $position)->increment('position');

            $task->forceFill([...$attributes, 'status' => $status, 'position' => $position])->save();

            return $task->load('assignee:id,name');
        });
    }

    public function deleteTask(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $this->column($task->project_id, $task->status)
                ->where('position', '>', $task->position)
                ->decrement('position');

            $task->delete();
        });
    }

    /**
     * @return Builder<Task>
     */
    private function column(int $projectId, TaskStatus $status): Builder
    {
        return Task::query()->where('project_id', $projectId)->where('status', $status);
    }
}
