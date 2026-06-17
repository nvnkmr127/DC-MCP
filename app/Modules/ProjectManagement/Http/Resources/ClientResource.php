<?php

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'industry' => $this->industry,
            'tier' => $this->tier,
            'status' => $this->status,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'id' => $this->manager->id,
                    'name' => $this->manager->name,
                    'email' => $this->manager->email,
                ];
            }),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
