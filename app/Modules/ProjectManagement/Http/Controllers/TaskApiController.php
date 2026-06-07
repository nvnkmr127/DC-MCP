<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Services\TaskService;
use App\Modules\ProjectManagement\Http\Requests\StoreTaskRequest;
use App\Modules\ProjectManagement\Http\Resources\TaskResource;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Http\Resources\TimeEntryResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

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
    protected TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $query = Task::where('organization_id', $orgId);

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
        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by'] = $request->user()->id;

        $task = $this->taskService->createTask($data);
        return ApiResponse::success(new TaskResource($task), 'Task created successfully.', [], 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOrg($task);
        $task->load(['project', 'sprint', 'milestone', 'assignee', 'creator']);
        return ApiResponse::success(new TaskResource($task));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOrg($task);
        $orgId = $request->user()->organization_id;
        $projectId = $task->project_id;

        $data = $request->validate([
            'sprint_id' => [
                'nullable',
                'uuid',
                Rule::exists('sprints', 'id')->where('project_id', $projectId),
            ],
            'milestone_id' => [
                'nullable',
                'uuid',
                Rule::exists('milestones', 'id')->where('project_id', $projectId),
            ],
            'parent_task_id' => [
                'nullable',
                'uuid',
                Rule::exists('tasks', 'id')
                    ->where('organization_id', $orgId)
                    ->where('project_id', $projectId),
            ],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:feature,bug,content,design,research,review,meeting,report,campaign_setup,ad_creative,seo_audit,email_sequence,other',
            'status' => 'nullable|in:backlog,todo,in_progress,in_review,blocked,done,cancelled',
            'priority' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'role_required' => 'nullable|in:ceo,project_manager,analyst,marketer,developer,designer,copywriter',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'sla_hours' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'meta' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ]);

        // If status is changed, validate transition then update via TaskService
        if (isset($data['status']) && $data['status'] !== $task->status) {
            $allowed = TASK_STATUS_TRANSITIONS[$task->status] ?? [];
            if (!in_array($data['status'], $allowed, true)) {
                return ApiResponse::error("Invalid status transition from '{$task->status}' to '{$data['status']}'.", [], 422);
            }
            $status = $data['status'];
            unset($data['status']);
            
            // Update other fields first
            if (!empty($data)) {
                $task->update($data);
            }
            
            $task = $this->taskService->updateTaskStatus($task, $status, $request->user());
        } else {
            $task->update($data);
        }

        $task->load(['project', 'sprint', 'milestone', 'assignee', 'creator']);
        return ApiResponse::success(new TaskResource($task), 'Task updated successfully.');
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOrg($task);

        if ($task->subtasks()->exists()) {
            return ApiResponse::error('Cannot delete a task that has subtasks. Delete or reassign subtasks first.', [], 422);
        }

        Log::warning('Task deleted', [
            'task_id'        => $task->id,
            'title'          => $task->title,
            'status'         => $task->status,
            'project_id'     => $task->project_id,
            'organization_id'=> $task->organization_id,
            'deleted_by'     => $request->user()->id,
        ]);

        $task->delete();
        return ApiResponse::success(null, 'Task deleted successfully.');
    }

    public function assign(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOrg($task);

        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $user = User::where('id', $request->user_id)
            ->where('organization_id', $request->user()->organization_id)
            ->firstOrFail();

        $task = $this->taskService->assignTask($task, $user, $request->user());

        return ApiResponse::success(new TaskResource($task), "Task successfully assigned to {$user->name}.");
    }

    public function logTime(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOrg($task);

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
        $this->authorizeOrg($task);

        $projectId = $task->project_id;
        $data = $request->validate([
            'sprint_id' => [
                'nullable',
                'uuid',
                Rule::exists('sprints', 'id')->where('project_id', $projectId),
            ],
            'milestone_id' => [
                'nullable',
                'uuid',
                Rule::exists('milestones', 'id')->where('project_id', $projectId),
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
