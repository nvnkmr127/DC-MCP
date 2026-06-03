<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Services\SprintService;
use App\Modules\ProjectManagement\Http\Requests\StoreSprintRequest;
use App\Modules\ProjectManagement\Http\Resources\SprintResource;
use App\Modules\ProjectManagement\Models\Project;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintApiController extends Controller
{
    protected SprintService $sprintService;

    public function __construct(SprintService $sprintService)
    {
        $this->sprintService = $sprintService;
    }

    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $query = Sprint::whereHas('project', fn ($q) => $q->where('organization_id', $orgId));

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        $sprints = $query->with('project')->get();
        return ApiResponse::success(SprintResource::collection($sprints));
    }

    public function store(StoreSprintRequest $request): JsonResponse
    {
        $project = Project::where('id', $request->project_id)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $sprint = $this->sprintService->createSprint($project, $request->validated());
        return ApiResponse::success(new SprintResource($sprint), 'Sprint created successfully.', [], 201);
    }

    public function show(Request $request, Sprint $sprint): JsonResponse
    {
        abort_if($sprint->project->organization_id !== $request->user()->organization_id, 403);
        $sprint->load('project');
        return ApiResponse::success(new SprintResource($sprint));
    }

    public function update(Request $request, Sprint $sprint): JsonResponse
    {
        abort_if($sprint->project->organization_id !== $request->user()->organization_id, 403);

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

    public function destroy(Request $request, Sprint $sprint): JsonResponse
    {
        abort_if($sprint->project->organization_id !== $request->user()->organization_id, 403);
        abort_if($sprint->status === 'active', 422, 'Cannot delete an active sprint. Complete it first.');
        $sprint->delete();
        return ApiResponse::success(null, 'Sprint deleted successfully.');
    }

    public function start(Request $request, Sprint $sprint): JsonResponse
    {
        abort_if($sprint->project->organization_id !== $request->user()->organization_id, 403);
        abort_if($sprint->status !== 'planning', 422, 'Only a sprint in planning can be started.');
        $sprint = $this->sprintService->startSprint($sprint);
        return ApiResponse::success(new SprintResource($sprint), 'Sprint started successfully.');
    }

    public function complete(Request $request, Sprint $sprint): JsonResponse
    {
        abort_if($sprint->project->organization_id !== $request->user()->organization_id, 403);
        abort_if($sprint->status !== 'active', 422, 'Only an active sprint can be completed.');

        $action       = $request->input('unfinished_task_action', 'backlog');
        $nextSprintId = $request->input('next_sprint_id');
        $nextSprint   = null;

        if ($action === 'next_sprint') {
            if (!$nextSprintId) {
                return ApiResponse::error('next_sprint_id is required when action is next_sprint.', [], 422);
            }
            $nextSprint = Sprint::where('id', $nextSprintId)
                ->whereHas('project', fn ($q) => $q->where('organization_id', $request->user()->organization_id))
                ->where('status', 'planning')
                ->first();

            if (!$nextSprint) {
                return ApiResponse::error('Target sprint not found or is not in planning status.', [], 422);
            }
        }

        $sprint = $this->sprintService->completeSprint($sprint, $action, $nextSprint);
        return ApiResponse::success(new SprintResource($sprint), 'Sprint completed successfully.');
    }
}
