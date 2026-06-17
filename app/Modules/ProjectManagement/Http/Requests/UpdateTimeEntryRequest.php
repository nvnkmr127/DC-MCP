<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('update', 'time_entry');
    }

    public function rules(): array
    {
        return [
            'description' => 'nullable|string|max:1000',
            'hours'       => 'nullable|numeric|min:0.01|max:24',
            'logged_date' => 'nullable|date|before_or_equal:today',
            'is_billable' => 'nullable|boolean',
        ];
    }
}
