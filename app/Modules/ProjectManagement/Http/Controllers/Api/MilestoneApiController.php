<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Http\Requests\StoreMilestoneRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateMilestoneRequest;
use App\Modules\ProjectManagement\Http\Resources\MilestoneResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Milestone::query()
            ->whereHas('project');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $milestones = $query->with(['project', 'sprint'])->get();
        return ApiResponse::success(MilestoneResource::collection($milestones));
    }

    public function store(StoreMilestoneRequest $request): JsonResponse
    {
        $data = $request->validated();

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
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403, 'Unauthorized action.');
        }

        $milestone->load(['project', 'sprint']);
        return ApiResponse::success(new MilestoneResource($milestone));
    }

    public function update(UpdateMilestoneRequest $request, Milestone $milestone): JsonResponse
    {
        $data = $request->validated();

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
        if (!$request->user()->hasPermission('delete', 'project')) {
            abort(403, 'Unauthorized action.');
        }

        $milestone->delete();
        return ApiResponse::success(null, 'Milestone deleted successfully.');
    }
}
