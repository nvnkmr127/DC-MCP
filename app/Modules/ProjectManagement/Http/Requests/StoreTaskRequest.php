<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = $this->user()->organization_id;

        return [
            'project_id'     => [
                'required', 'uuid',
                Rule::exists('projects', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'sprint_id'      => [
                'nullable', 'uuid',
                Rule::exists('sprints', 'id')->where('project_id', $this->input('project_id')),
            ],
            'milestone_id'   => [
                'nullable', 'uuid',
                Rule::exists('milestones', 'id')->where('project_id', $this->input('project_id')),
            ],
            'parent_task_id' => [
                'nullable', 'uuid',
                Rule::exists('tasks', 'id')
                    ->where('organization_id', $orgId)
                    ->where('project_id', $this->input('project_id')),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:feature,bug,content,design,research,review,meeting,report,campaign_setup,ad_creative,seo_audit,email_sequence,other',
            'status' => 'nullable|in:backlog,todo,in_progress,in_review,blocked,done,cancelled',
            'priority' => 'required|in:low,medium,high,critical',
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
        ];
    }
}
