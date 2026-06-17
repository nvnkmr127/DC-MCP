<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\User;
use App\Shared\Enums\TaskType;
use App\Shared\Enums\TaskStatus;
use App\Shared\Enums\TaskPriority;
use App\Shared\Enums\RoleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('update', 'task');
    }

    public function rules(): array
    {
        $task = $this->route('task');
        $projectId = $task ? $task->project_id : null;

        return [
            'sprint_id' => [
                'nullable', 'uuid',
                Rule::exists(Sprint::class, 'id')->where('project_id', $projectId),
            ],
            'milestone_id' => [
                'nullable', 'uuid',
                Rule::exists(Milestone::class, 'id')->where('project_id', $projectId),
            ],
            'parent_task_id' => [
                'nullable', 'uuid',
                Rule::exists(Task::class, 'id')
                    ->where('project_id', $projectId),
            ],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => ['nullable', new Enum(TaskType::class)],
            'status' => ['nullable', new Enum(TaskStatus::class)],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists(User::class, 'id')->whereNull('deleted_at'),
            ],
            'role_required' => ['nullable', new Enum(RoleType::class)],
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'sla_hours' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'meta' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ];
    }
}
