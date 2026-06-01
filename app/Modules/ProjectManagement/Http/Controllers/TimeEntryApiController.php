<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Http\Requests\StoreTimeEntryRequest;
use App\Modules\ProjectManagement\Http\Resources\TimeEntryResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeEntryApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TimeEntry::query();
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('task_id')) {
            $query->where('task_id', $request->task_id);
        }

        $entries = $query->with(['task', 'user', 'project'])->get();
        return ApiResponse::success(TimeEntryResource::collection($entries));
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $task = Task::findOrFail($request->task_id);
        
        $entry = TimeEntry::create([
            'organization_id' => $request->user()->organization_id,
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'project_id' => $task->project_id,
            'description' => $request->description,
            'hours' => $request->hours,
            'logged_date' => $request->logged_date,
            'is_billable' => $request->input('is_billable', true),
        ]);

        $task->increment('actual_hours', $request->hours);

        return ApiResponse::success(new TimeEntryResource($entry), 'Time entry created successfully.', [], 201);
    }

    public function update(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        $data = $request->validate([
            'description' => 'nullable|string|max:1000',
            'hours' => 'nullable|numeric|min:0.01',
            'logged_date' => 'nullable|date',
            'is_billable' => 'nullable|boolean',
        ]);

        $oldHours = $timeEntry->hours;
        $timeEntry->update($data);

        if (isset($data['hours']) && $oldHours !== $timeEntry->hours) {
            $diff = $timeEntry->hours - $oldHours;
            $timeEntry->task()->increment('actual_hours', $diff);
        }

        return ApiResponse::success(new TimeEntryResource($timeEntry), 'Time entry updated successfully.');
    }

    public function destroy(TimeEntry $timeEntry): JsonResponse
    {
        $timeEntry->task()->decrement('actual_hours', $timeEntry->hours);
        $timeEntry->delete();
        return ApiResponse::success(null, 'Time entry deleted successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $query = DB::table('time_entries')
            ->where('organization_id', $request->user()->organization_id);

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $summary = $query->select(
            'user_id',
            'project_id',
            'logged_date',
            DB::raw('SUM(hours) as total_hours')
        )
        ->groupBy('user_id', 'project_id', 'logged_date')
        ->get();

        return ApiResponse::success($summary);
    }
}
