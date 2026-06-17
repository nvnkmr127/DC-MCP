<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Shared\Enums\ClientStatus;
use App\Shared\Enums\ClientTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create', 'client');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'company'     => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'website'     => ['nullable', 'url'],
            'industry'    => ['nullable', 'string', 'max:100'],
            'tier'        => ['nullable', new Enum(ClientTier::class)],
            'status'      => ['nullable', new Enum(ClientStatus::class)],
            'notes'       => ['nullable', 'string'],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'metadata'    => ['nullable', 'array'],
        ];
    }
}
