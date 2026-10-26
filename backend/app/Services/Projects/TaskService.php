<?php

namespace App\Services\Projects;

use App\DTOs\Tasks\TaskData;
use App\Enums\TaskStatus;
use App\Exceptions\Projects\ProjectClosedException;
use App\Exceptions\Tasks\AssigneeNotMemberException;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\TaskAssignedNotification;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\TaskRepository;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly CompanyRepository $companies,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return Collection<int, Task>
     */
    public function board(Project $project): Collection
    {
        return $this->tasks->forProject($project);
    }

    /**
     * @return Collection<int, Task>
     */
    public function mine(User $user): Collection
    {
        return $this->tasks->openAssignedTo($user);
    }

    public function create(Project $project, User $creator, TaskData $data): Task
    {
        if (! $project->status->acceptsTasks()) {
            throw new ProjectClosedException;
        }

        $assignee = $this->assignee($data->assignee_id);

        $task = $this->tasks->createTask([
            ...$data->toArray(),
            'project_id' => $project->id,
            'created_by' => $creator->id,
            'position' => $this->tasks->nextPosition($project, $data->status),
            'completed_at' => $data->status->isDone() ? now() : null,
        ]);

        $this->notifyAssignee($assignee, $creator, $task, $project);

        return $task;
    }

    /**
     * Edita os dados da tarefa. Se a coluna mudar, ela vai para o fim da nova coluna.
     */
    public function update(Project $project, Task $task, User $actor, TaskData $data): Task
    {
        $assignee = $this->assignee($data->assignee_id);
        $previousAssignee = $task->assignee_id;

        $attributes = $data->toArray();
        unset($attributes['status']);
        $task = $this->tasks->updateTask($task, $attributes);

        if ($task->status !== $data->status) {
            $task = $this->move($project, $task, $data->status, $this->tasks->nextPosition($project, $data->status));
        }

        if ($assignee !== null && $assignee->id !== $previousAssignee) {
            $this->notifyAssignee($assignee, $actor, $task, $project);
        }

        return $task;
    }

    public function move(Project $project, Task $task, TaskStatus $status, int $position): Task
    {
        $completedAt = match (true) {
            ! $status->isDone() => null,
            $task->status->isDone() => $task->completed_at,
            default => now(),
        };

        return $this->tasks->move($task, $status, $position, ['completed_at' => $completedAt]);
    }

    public function delete(Task $task): void
    {
        $this->tasks->deleteTask($task);
    }

    private function assignee(?int $userId): ?User
    {
        if ($userId === null) {
            return null;
        }

        return $this->companies->findMember($this->tenant->company(), $userId)
            ?? throw new AssigneeNotMemberException;
    }

    /**
     * Ninguém recebe e-mail por atribuir uma tarefa a si mesmo.
     */
    private function notifyAssignee(?User $assignee, User $actor, Task $task, Project $project): void
    {
        if ($assignee === null || $assignee->id === $actor->id) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification(
            taskTitle: $task->title,
            projectName: $project->name,
            projectUlid: $project->ulid,
            companyName: $this->tenant->company()->name,
            assignerName: $actor->name,
            dueOn: $task->due_on?->format('d/m/Y'),
        ));
    }
}
