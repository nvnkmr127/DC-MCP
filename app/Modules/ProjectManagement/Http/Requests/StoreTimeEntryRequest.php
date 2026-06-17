<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create', 'time_entry');
    }

    public function rules(): array
    {
        return [
            'task_id'     => [
                'required', 'uuid',
                \Illuminate\Validation\Rule::exists(Task::class, 'id'),
            ],
            'hours'       => 'required|numeric|min:0.01|max:24',
            'description' => 'required|string|max:1000',
            'logged_date' => 'required|date|before_or_equal:today',
            'is_billable' => 'nullable|boolean',
        ];
    }
}
