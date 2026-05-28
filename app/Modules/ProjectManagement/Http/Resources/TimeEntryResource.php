<?php

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'task_id' => $this->task_id,
            'task_title' => $this->task ? $this->task->title : null,
            'user_id' => $this->user_id,
            'user_name' => $this->user ? $this->user->name : null,
            'project_id' => $this->project_id,
            'project_name' => $this->project ? $this->project->name : null,
            'description' => $this->description,
            'hours' => (float) $this->hours,
            'logged_date' => $this->logged_date ? $this->logged_date->toDateString() : null,
            'is_billable' => (bool) $this->is_billable,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
