<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Http\Requests\StoreMilestoneRequest;
use App\Modules\ProjectManagement\Http\Resources\MilestoneResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Milestone::query();
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        $milestones = $query->with(['project', 'sprint'])->get();
        return ApiResponse::success(MilestoneResource::collection($milestones));
    }

    public function store(StoreMilestoneRequest $request): JsonResponse
    {
        $milestone = Milestone::create($request->validated());
        return ApiResponse::success(new MilestoneResource($milestone), 'Milestone created successfully.', [], 201);
    }

    public function show(Milestone $milestone): JsonResponse
    {
        $milestone->load(['project', 'sprint']);
        return ApiResponse::success(new MilestoneResource($milestone));
    }

    public function update(Request $request, Milestone $milestone): JsonResponse
    {
        $data = $request->validate([
            'sprint_id' => 'nullable|uuid|exists:sprints,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'status' => 'nullable|in:pending,in_progress,completed,missed',
        ]);

        $milestone->update($data);
        return ApiResponse::success(new MilestoneResource($milestone), 'Milestone updated successfully.');
    }

    public function destroy(Milestone $milestone): JsonResponse
    {
        $milestone->delete();
        return ApiResponse::success(null, 'Milestone deleted successfully.');
    }
}
