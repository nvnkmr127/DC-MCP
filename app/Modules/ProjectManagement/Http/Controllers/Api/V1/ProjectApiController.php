<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Services\ProjectService;
use App\Modules\ProjectManagement\Http\Requests\StoreProjectRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateProjectRequest;
use App\Modules\ProjectManagement\Http\Resources\ProjectResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectApiController extends Controller
{
    public function __construct(
        protected ProjectService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $projects = Project::with(['client', 'manager'])->get();
        return ApiResponse::success(ProjectResource::collection($projects));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($request->validated());
        return ApiResponse::success(new ProjectResource($project), 'Project created successfully.', [], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403, 'Unauthorized action.');
        }

        $project->load(['client', 'manager']);
        return ApiResponse::success(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $updated = $this->projectService->updateProject($project, $request->validated());
        return ApiResponse::success(new ProjectResource($updated), 'Project updated successfully.');
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->hasPermission('delete', 'project')) {
            abort(403, 'Unauthorized action.');
        }

        $this->projectService->archiveProject($project);
        return ApiResponse::success(null, 'Project archived successfully.');
    }

    public function stats(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403, 'Unauthorized action.');
        }

        $stats = $this->projectService->getProjectStats($project);
        return ApiResponse::success($stats);
    }

    public function teamWorkload(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403, 'Unauthorized action.');
        }

        $workload = $this->projectService->getTeamWorkload($request->user()->organization);
        return ApiResponse::success($workload);
    }
}
