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
        return [
            'task_id' => 'required|uuid|exists:tasks,id',
            'hours' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:1000',
            'logged_date' => 'required|date',
            'is_billable' => 'nullable|boolean',
        ];
    }
}
