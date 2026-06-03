<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'nullable|uuid|exists:clients,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:seo,social_media,performance_ads,web_dev,app_dev,content,brand,whatsapp,email_marketing,ecommerce',
            'status' => 'nullable|in:planning,draft,active,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,critical',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'budget_used' => 'nullable|numeric|min:0',
            'project_manager_id' => 'nullable|uuid|exists:users,id',
            'settings' => 'nullable|array',
            'tags' => 'nullable|array',
        ];
    }
}
