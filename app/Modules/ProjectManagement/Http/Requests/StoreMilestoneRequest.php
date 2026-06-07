<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMilestoneRequest extends FormRequest
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
            'sprint_id' => [
                'nullable',
                'uuid',
                Rule::exists('sprints', 'id')->where('project_id', $this->input('project_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,in_progress,completed,missed'],
        ];
    }
}
