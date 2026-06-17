<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Shared\Enums\MilestoneStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create', 'project'); // or milestone depending on the system rules
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'uuid',
                Rule::exists(Project::class, 'id')->whereNull('deleted_at'),
            ],
            'sprint_id' => [
                'nullable',
                'uuid',
                Rule::exists(Sprint::class, 'id')->where('project_id', $this->input('project_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['nullable', new Enum(MilestoneStatus::class)],
        ];
    }
}
