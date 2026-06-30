<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = $this->user()->organization_id;

        return [
            'client_id' => [
                'nullable',
                'uuid',
                Rule::exists('clients', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'goal_id' => [
                'nullable',
                'uuid',
                Rule::exists('goals', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', Rule::in([
                'seo', 'social_media', 'performance_ads', 'web_dev', 'app_dev',
                'content', 'brand', 'whatsapp', 'email_marketing', 'ecommerce',
            ])],
            'status' => ['nullable', Rule::in(['draft', 'planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'budget_used' => ['nullable', 'numeric', 'min:0'],
            'project_manager_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'settings' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
        ];
    }
}
