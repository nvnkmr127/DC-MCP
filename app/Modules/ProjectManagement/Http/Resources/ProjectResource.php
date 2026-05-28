<?php

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'client_id' => $this->client_id,
            'client_name' => $this->client ? $this->client->name : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'start_date' => $this->start_date ? $this->start_date->toDateString() : null,
            'end_date' => $this->end_date ? $this->end_date->toDateString() : null,
            'actual_end_date' => $this->actual_end_date ? $this->actual_end_date->toDateString() : null,
            'budget' => (float) $this->budget,
            'budget_used' => (float) $this->budget_used,
            'project_manager_id' => $this->project_manager_id,
            'manager_name' => $this->manager ? $this->manager->name : null,
            'settings' => $this->settings,
            'tags' => $this->tags,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
