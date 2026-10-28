<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Http\Requests\Tasks\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\TaskService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Tarefas', weight: 8)]
class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        return TaskResource::collection($this->tasks->board($project));
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return TaskResource::collection($this->tasks->mine($user));
    }

    public function store(TaskRequest $request, Project $project): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return TaskResource::make($this->tasks->create($project, $user, $request->toData()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(TaskRequest $request, Project $project, Task $task): TaskResource
    {
        /** @var User $user */
        $user = $request->user();

        return TaskResource::make($this->tasks->update($project, $task, $user, $request->toData()));
    }

    public function move(MoveTaskRequest $request, Project $project, Task $task): TaskResource
    {
        return TaskResource::make($this->tasks->move($project, $task, $request->status(), $request->position()));
    }

    public function destroy(Project $project, Task $task): Response
    {
        $this->tasks->delete($task);

        return response()->noContent();
    }
}
