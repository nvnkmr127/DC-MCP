<?php

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_name' => $this->project ? $this->project->name : null,
            'sprint_id' => $this->sprint_id,
            'sprint_name' => $this->sprint ? $this->sprint->name : null,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'due_date' => $this->due_date ? $this->due_date->toDateString() : null,
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
