<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Services\TaskService;
use App\Modules\ProjectManagement\Http\Requests\StoreTaskRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateTaskRequest;
use App\Modules\ProjectManagement\Http\Resources\TaskResource;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Http\Resources\TimeEntryResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Shared\Enums\TaskStatus;

// Allowed status transitions: from → [allowed targets]
// Prevents re-opening cancelled tasks or skipping review steps arbitrarily.
const TASK_STATUS_TRANSITIONS = [
    'backlog'     => ['todo', 'in_progress', 'cancelled'],
    'todo'        => ['in_progress', 'backlog', 'cancelled'],
    'in_progress' => ['in_review', 'done', 'blocked', 'todo', 'cancelled'],
    'in_review'   => ['in_progress', 'done', 'blocked', 'cancelled'],
    'blocked'     => ['in_progress', 'todo', 'cancelled'],
    'done'        => ['in_progress', 'cancelled'],
    'cancelled'   => [],
];

class TaskApiController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Task::query();

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('sprint_id')) {
            $query->where('sprint_id', $request->sprint_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $tasks = $query->with(['project', 'sprint', 'milestone', 'assignee', 'creator'])->get();
        return ApiResponse::success(TaskResource::collection($tasks));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $task = $this->taskService->createTask($data);
        return ApiResponse::success(new TaskResource($task), 'Task created successfully.', [], 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        if (!$request->user()->hasPermission('view', 'task')) {
            abort(403, 'Unauthorized action.');
        }

        $task->load(['project', 'sprint', 'milestone', 'assignee', 'creator']);
        return ApiResponse::success(new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $data = $request->validated();

        // If status is changed, validate transition then update via TaskService
        if (isset($data['status']) && $data['status'] !== $task->status) {
            $currentStatusValue = $task->status instanceof TaskStatus ? $task->status->value : $task->status;
            $newStatusValue = $data['status'] instanceof TaskStatus ? $data['status']->value : $data['status'];

            $allowed = TASK_STATUS_TRANSITIONS[$currentStatusValue] ?? [];
            if (!in_array($newStatusValue, $allowed, true)) {
                return ApiResponse::error("Invalid status transition from '{$currentStatusValue}' to '{$newStatusValue}'.", [], 422);
            }
            $status = $newStatusValue;
            unset($data['status']);
            
            // Update other fields first
            if (!empty($data)) {
                $task->update($data);
            }
            
            $task = $this->taskService->updateTaskStatus($task, $status, $request->user());
        } else {
            if (!empty($data)) {
                $task->update($data);
            }
        }

        $task->load(['project', 'sprint', 'milestone', 'assignee', 'creator']);
        return ApiResponse::success(new TaskResource($task), 'Task updated successfully.');
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        if (!$request->user()->hasPermission('delete', 'task')) {
            abort(403, 'Unauthorized action.');
        }

        if ($task->subtasks()->exists()) {
            return ApiResponse::error('Cannot delete a task that has subtasks. Delete or reassign subtasks first.', [], 422);
        }

        Log::warning('Task deleted', [
            'task_id'        => $task->id,
            'title'          => $task->title,
            'status'         => $task->status,
            'project_id'     => $task->project_id,
            'deleted_by'     => $request->user()->id,
        ]);

        $task->delete();
        return ApiResponse::success(null, 'Task deleted successfully.');
    }

    public function assign(Request $request, Task $task): JsonResponse
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $user = User::where('id', $request->user_id)->firstOrFail();

        $task = $this->taskService->assignTask($task, $user, $request->user());

        return ApiResponse::success(new TaskResource($task), "Task successfully assigned to {$user->name}.");
    }

    public function logTime(Request $request, Task $task): JsonResponse
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'hours' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:1000',
            'logged_date' => 'required|date',
        ]);

        $entry = $this->taskService->logTime(
            $task,
            $request->user(),
            (float) $request->hours,
            $request->description,
            Carbon::parse($request->logged_date)
        );

        return ApiResponse::success(new TimeEntryResource($entry), 'Time logged successfully.', [], 201);
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403, 'Unauthorized action.');
        }

        $projectId = $task->project_id;
        $data = $request->validate([
            'sprint_id' => [
                'nullable',
                'uuid',
                Rule::exists(\App\Modules\ProjectManagement\Models\Sprint::class, 'id')->where('project_id', $projectId),
            ],
            'milestone_id' => [
                'nullable',
                'uuid',
                Rule::exists(\App\Modules\ProjectManagement\Models\Milestone::class, 'id')->where('project_id', $projectId),
            ],
        ]);

        if (array_key_exists('sprint_id', $data)) {
            $task->sprint_id = $data['sprint_id'];
        }
        if (array_key_exists('milestone_id', $data)) {
            $task->milestone_id = $data['milestone_id'];
        }
        $task->save();

        return ApiResponse::success(new TaskResource($task), 'Task moved successfully.');
    }
}
