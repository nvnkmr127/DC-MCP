<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = $this->user()->organization_id;

        return [
            'task_id'     => [
                'required', 'uuid',
                \Illuminate\Validation\Rule::exists('tasks', 'id')->where('organization_id', $orgId),
            ],
            'hours'       => 'required|numeric|min:0.01|max:24',
            'description' => 'required|string|max:1000',
            'logged_date' => 'required|date|before_or_equal:today',
            'is_billable' => 'nullable|boolean',
        ];
    }
}
