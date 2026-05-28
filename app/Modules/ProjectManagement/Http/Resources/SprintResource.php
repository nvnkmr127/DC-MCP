<?php

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_name' => $this->project ? $this->project->name : null,
            'name' => $this->name,
            'goal' => $this->goal,
            'status' => $this->status,
            'start_date' => $this->start_date ? $this->start_date->toDateString() : null,
            'end_date' => $this->end_date ? $this->end_date->toDateString() : null,
            'velocity_planned' => $this->velocity_planned,
            'velocity_actual' => $this->velocity_actual,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
