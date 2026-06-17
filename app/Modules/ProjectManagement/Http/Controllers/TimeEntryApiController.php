<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Http\Requests\StoreTimeEntryRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateTimeEntryRequest;
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

        $entry = DB::transaction(function () use ($task, $request) {
            $entry = TimeEntry::create([
                'task_id'         => $task->id,
                'user_id'         => $request->user()->id,
                'project_id'      => $task->project_id,
                'description'     => $request->description,
                'hours'           => $request->hours,
                'logged_date'     => $request->logged_date,
                'is_billable'     => $request->input('is_billable', true),
            ]);

            $task->increment('actual_hours', $request->hours);

            return $entry;
        });

        return ApiResponse::success(new TimeEntryResource($entry), 'Time entry created successfully.', [], 201);
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $data = $request->validated();

        $updated = DB::transaction(function () use ($timeEntry, $data) {
            // Lock the row so concurrent requests see consistent old hours
            $fresh    = TimeEntry::lockForUpdate()->findOrFail($timeEntry->id);
            $oldHours = (float) $fresh->hours;

            $fresh->update($data);

            if (isset($data['hours'])) {
                $diff = (float) $data['hours'] - $oldHours;
                if ($diff !== 0.0) {
                    $task = $fresh->task()->lockForUpdate()->first();
                    $task?->increment('actual_hours', $diff);
                }
            }

            return $fresh;
        });

        return ApiResponse::success(new TimeEntryResource($updated), 'Time entry updated successfully.');
    }

    public function destroy(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        if (!$request->user()->hasPermission('delete', 'time_entry')) {
            abort(403, 'Unauthorized action.');
        }

        DB::transaction(function () use ($timeEntry) {
            $task = $timeEntry->task()->lockForUpdate()->first();
            $task?->decrement('actual_hours', $timeEntry->hours);
            $timeEntry->delete();
        });

        return ApiResponse::success(null, 'Time entry deleted successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $query = TimeEntry::query();

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
