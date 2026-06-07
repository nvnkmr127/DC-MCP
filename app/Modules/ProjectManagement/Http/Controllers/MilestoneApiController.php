<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Http\Requests\StoreMilestoneRequest;
use App\Modules\ProjectManagement\Http\Resources\MilestoneResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $query = Milestone::query()
            ->whereHas('project', fn ($q) => $q->where('organization_id', $orgId));

        if ($request->filled('project_id')) {
            $this->authorizeProjectId($request->project_id);
            $query->where('project_id', $request->project_id);
        }

        $milestones = $query->with(['project', 'sprint'])->get();
        return ApiResponse::success(MilestoneResource::collection($milestones));
    }

    public function store(StoreMilestoneRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeProjectId($data['project_id']);

        if (!empty($data['sprint_id'])) {
            $isValid = Sprint::where('id', $data['sprint_id'])
                ->where('project_id', $data['project_id'])
                ->exists();

            if (!$isValid) {
                return ApiResponse::error('sprint_id must belong to the same project as project_id.', [], 422);
            }
        }

        $milestone = Milestone::create($data);
        return ApiResponse::success(new MilestoneResource($milestone), 'Milestone created successfully.', [], 201);
    }

    public function show(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeProjectId($milestone->project_id);
        $milestone->load(['project', 'sprint']);
        return ApiResponse::success(new MilestoneResource($milestone));
    }

    public function update(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeProjectId($milestone->project_id);

        $data = $request->validate([
            'sprint_id' => 'nullable|uuid|exists:sprints,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'status' => 'nullable|in:pending,in_progress,completed,missed',
        ]);

        if (array_key_exists('sprint_id', $data) && !empty($data['sprint_id'])) {
            $isValid = Sprint::where('id', $data['sprint_id'])
                ->where('project_id', $milestone->project_id)
                ->exists();

            if (!$isValid) {
                return ApiResponse::error('sprint_id must belong to the same project as this milestone.', [], 422);
            }
        }

        $milestone->update($data);
        return ApiResponse::success(new MilestoneResource($milestone), 'Milestone updated successfully.');
    }

    public function destroy(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeProjectId($milestone->project_id);
        $milestone->delete();
        return ApiResponse::success(null, 'Milestone deleted successfully.');
    }
}
