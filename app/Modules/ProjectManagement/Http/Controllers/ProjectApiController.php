<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

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
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function index(Request $request): JsonResponse
    {
        $projects = Project::with(['client', 'manager'])
            ->where('organization_id', $request->user()->organization_id)
            ->get();
        return ApiResponse::success(ProjectResource::collection($projects));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['organization_id'] = $request->user()->organization_id;

        $project = $this->projectService->createProject($data);
        return ApiResponse::success(new ProjectResource($project), 'Project created successfully.', [], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOrg($project);
        $project->load(['client', 'manager']);
        return ApiResponse::success(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorizeOrg($project);
        $updated = $this->projectService->updateProject($project, $request->validated());
        return ApiResponse::success(new ProjectResource($updated), 'Project updated successfully.');
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOrg($project);
        $this->projectService->archiveProject($project);
        return ApiResponse::success(null, 'Project archived successfully.');
    }

    public function stats(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOrg($project);
        $stats = $this->projectService->getProjectStats($project);
        return ApiResponse::success($stats);
    }

    public function teamWorkload(Request $request): JsonResponse
    {
        $workload = $this->projectService->getTeamWorkload($request->user()->organization);
        return ApiResponse::success($workload);
    }
}
