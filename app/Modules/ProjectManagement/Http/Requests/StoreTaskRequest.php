<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|uuid|exists:projects,id',
            'sprint_id' => 'nullable|uuid|exists:sprints,id',
            'milestone_id' => 'nullable|uuid|exists:milestones,id',
            'parent_task_id' => 'nullable|uuid|exists:tasks,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:feature,bug,content,design,research,review,meeting,report,campaign_setup,ad_creative,seo_audit,email_sequence,other',
            'status' => 'nullable|in:backlog,todo,in_progress,in_review,blocked,done,cancelled',
            'priority' => 'required|in:low,medium,high,critical',
            'assigned_to' => 'nullable|uuid|exists:users,id',
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
