<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ListProjectsRequest;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(ListProjectsRequest $request): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->projects->list($request->toFilters()));
    }

    public function show(Project $project): ProjectResource
    {
        return ProjectResource::make($this->projects->show($project));
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        return ProjectResource::make($this->projects->create($request->toData()))->response()->setStatusCode(201);
    }

    public function update(ProjectRequest $request, Project $project): ProjectResource
    {
        return ProjectResource::make($this->projects->update($project, $request->toData()));
    }

    public function destroy(Project $project): Response
    {
        $this->projects->delete($project);

        return response()->noContent();
    }
}
