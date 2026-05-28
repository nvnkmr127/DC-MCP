<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMilestoneRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'status' => 'nullable|in:pending,in_progress,completed,missed',
        ];
    }
}
