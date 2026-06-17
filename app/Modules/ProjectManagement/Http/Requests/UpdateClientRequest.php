<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Shared\Enums\ClientStatus;
use App\Shared\Enums\ClientTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('update', 'client');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'company'     => ['sometimes', 'string', 'max:255'],
            'email'       => ['sometimes', 'email', 'max:255'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:20'],
            'website'     => ['sometimes', 'nullable', 'url'],
            'industry'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'tier'        => ['sometimes', new Enum(ClientTier::class)],
            'status'      => ['sometimes', new Enum(ClientStatus::class)],
            'notes'       => ['sometimes', 'nullable', 'string'],
            'assigned_to' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'metadata'    => ['sometimes', 'array'],
        ];
    }
}
