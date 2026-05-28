<?php

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Calculate SLA status
        $slaStatus = 'na';
        if ($this->sla_hours !== null) {
            if ($this->sla_breached_at) {
                $slaStatus = 'breached';
            } else if (in_array($this->status, ['done', 'cancelled'])) {
                $slaStatus = 'ok';
            } else {
                $deadline = $this->created_at->addHours($this->sla_hours);
                if (now()->greaterThanOrEqualTo($deadline)) {
                    $slaStatus = 'breached';
                } else if (now()->greaterThanOrEqualTo($deadline->subHours(4))) {
                    $slaStatus = 'warning';
                } else {
                    $slaStatus = 'ok';
                }
            }
        }

        // Calculate time logged
        $timeLogged = (float) DB::table('time_entries')
            ->where('task_id', $this->id)
            ->sum('hours');

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'project_id' => $this->project_id,
            'project_name' => $this->project ? $this->project->name : null,
            'sprint_id' => $this->sprint_id,
            'sprint_name' => $this->sprint ? $this->sprint->name : null,
            'milestone_id' => $this->milestone_id,
            'milestone_name' => $this->milestone ? $this->milestone->name : null,
            'parent_task_id' => $this->parent_task_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->assignee ? $this->assignee->name : null,
            'assignee_avatar' => $this->assignee ? $this->assignee->avatar_url : null,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator ? $this->creator->name : null,
            'role_required' => $this->role_required,
            'due_date' => $this->due_date ? $this->due_date->toDateString() : null,
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
            'estimated_hours' => (float) $this->estimated_hours,
            'actual_hours' => (float) $this->actual_hours,
            'sla_hours' => $this->sla_hours,
            'sla_breached_at' => $this->sla_breached_at ? $this->sla_breached_at->toIso8601String() : null,
            'sla_status' => $slaStatus,
            'tags' => $this->tags,
            'meta' => $this->meta,
            'sort_order' => $this->sort_order,
            'subtask_count' => $this->subtasks()->count(),
            'time_logged' => $timeLogged,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
