<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Shared\Enums\SprintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('update', 'sprint');
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'goal' => 'nullable|string',
            'status' => ['nullable', new Enum(SprintStatus::class)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'velocity_planned' => 'nullable|integer|min:0',
            'velocity_actual' => 'nullable|integer|min:0',
        ];
    }
}
