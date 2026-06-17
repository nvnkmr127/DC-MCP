<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Modules\ProjectManagement\Models\Sprint;
use App\Shared\Enums\MilestoneStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('update', 'project'); // or milestone
    }

    public function rules(): array
    {
        $milestone = $this->route('milestone');
        $projectId = $milestone ? $milestone->project_id : null;

        return [
            'sprint_id' => [
                'nullable',
                'uuid',
                Rule::exists(Sprint::class, 'id')->where('project_id', $projectId),
            ],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'status' => ['nullable', new Enum(MilestoneStatus::class)],
        ];
    }
}
