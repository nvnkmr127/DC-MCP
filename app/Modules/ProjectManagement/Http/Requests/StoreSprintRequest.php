<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'uuid',
                Rule::exists('projects', 'id')
                    ->where('organization_id', $this->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'goal' => 'nullable|string',
            'status' => 'nullable|in:planning,active,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'velocity_planned' => 'nullable|integer|min:0',
            'velocity_actual' => 'nullable|integer|min:0',
        ];
    }
}
