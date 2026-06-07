<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clients = Client::with('manager')
            ->where('organization_id', $request->user()->organization_id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('tier'), fn ($q) => $q->where('tier', $request->tier))
            ->orderBy('name')
            ->paginate(20);

        return ApiResponse::paginated($clients);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'company'     => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'website'     => ['nullable', 'url'],
            'industry'    => ['nullable', 'string', 'max:100'],
            'tier'        => ['nullable', 'string', 'in:basic,standard,premium,enterprise'],
            'status'      => ['nullable', 'string', 'in:active,inactive,prospect,churned'],
            'notes'       => ['nullable', 'string'],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'metadata'    => ['nullable', 'array'],
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['status'] = $data['status'] ?? 'active';

        $client = Client::create($data);

        return ApiResponse::success($client->load('manager'), 'Client created.', [], 201);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $this->authorizeOrg($client);
        $client->load(['manager']);
        $projects = $client->projects()->orderByDesc('updated_at')->paginate(50);

        $data = $client->toArray();
        $data['projects'] = $projects->items();

        return ApiResponse::success($data, 'Success', [
            'projects_pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorizeOrg($client);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'company'     => ['sometimes', 'string', 'max:255'],
            'email'       => ['sometimes', 'email', 'max:255'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:20'],
            'website'     => ['sometimes', 'nullable', 'url'],
            'industry'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'tier'        => ['sometimes', 'string', 'in:basic,standard,premium,enterprise'],
            'status'      => ['sometimes', 'string', 'in:active,inactive,prospect,churned'],
            'notes'       => ['sometimes', 'nullable', 'string'],
            'assigned_to' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'metadata'    => ['sometimes', 'array'],
        ]);

        $client->update($data);

        return ApiResponse::success($client->fresh(['manager']), 'Client updated.');
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->authorizeOrg($client);
        $client->delete();
        return ApiResponse::success(null, 'Client archived.');
    }
}
