<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Services\SprintService;
use App\Modules\ProjectManagement\Http\Requests\StoreSprintRequest;
use App\Modules\ProjectManagement\Http\Resources\SprintResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    protected SprintService $sprintService;

    public function __construct(SprintService $sprintService)
    {
        $this->sprintService = $sprintService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Sprint::query();
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        $sprints = $query->with('project')->get();
        return ApiResponse::success(SprintResource::collection($sprints));
    }

    public function store(StoreSprintRequest $request): JsonResponse
    {
        $project = \App\Modules\ProjectManagement\Models\Project::findOrFail($request->project_id);
        $sprint = $this->sprintService->createSprint($project, $request->validated());
        return ApiResponse::success(new SprintResource($sprint), 'Sprint created successfully.', [], 201);
    }

    public function show(Sprint $sprint): JsonResponse
    {
        $sprint->load('project');
        return ApiResponse::success(new SprintResource($sprint));
    }

    public function update(Request $request, Sprint $sprint): JsonResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'goal' => 'nullable|string',
            'status' => 'nullable|in:planning,active,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'velocity_planned' => 'nullable|integer',
            'velocity_actual' => 'nullable|integer',
        ]);

        $sprint->update($data);
        return ApiResponse::success(new SprintResource($sprint), 'Sprint updated successfully.');
    }

    public function destroy(Sprint $sprint): JsonResponse
    {
        $sprint->delete();
        return ApiResponse::success(null, 'Sprint deleted successfully.');
    }

    public function start(Sprint $sprint): JsonResponse
    {
        $sprint = $this->sprintService->startSprint($sprint);
        return ApiResponse::success(new SprintResource($sprint), 'Sprint started successfully.');
    }

    public function complete(Request $request, Sprint $sprint): JsonResponse
    {
        $action = $request->input('unfinished_task_action', 'backlog');
        $nextSprintId = $request->input('next_sprint_id');
        $nextSprint = $nextSprintId ? Sprint::find($nextSprintId) : null;

        $sprint = $this->sprintService->completeSprint($sprint, $action, $nextSprint);
        return ApiResponse::success(new SprintResource($sprint), 'Sprint completed successfully.');
    }
}
