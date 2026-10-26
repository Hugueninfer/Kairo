<?php

namespace App\Repositories\Contracts;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Prettus\Repository\Contracts\RepositoryInterface;

interface TaskRepository extends RepositoryInterface
{
    /**
     * Tarefas do projeto na ordem do quadro (coluna e posição).
     *
     * @return Collection<int, Task>
     */
    public function forProject(Project $project): Collection;

    /**
     * Tarefas abertas atribuídas ao usuário na empresa ativa.
     *
     * @return Collection<int, Task>
     */
    public function openAssignedTo(User $user): Collection;

    public function findByUlid(string $ulid): ?Task;

    public function nextPosition(Project $project, TaskStatus $status): int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTask(array $attributes): Task;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTask(Task $task, array $attributes): Task;

    /**
     * Move a tarefa para a coluna e posição pedidas, reorganizando as outras.
     *
     * @param  array<string, mixed>  $attributes  campos extras gravados junto (ex.: completed_at)
     */
    public function move(Task $task, TaskStatus $status, int $position, array $attributes = []): Task;

    public function deleteTask(Task $task): void;
}
