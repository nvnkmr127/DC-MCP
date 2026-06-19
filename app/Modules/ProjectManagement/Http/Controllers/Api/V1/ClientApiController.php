<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Http\Requests\StoreClientRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateClientRequest;
use App\Modules\ProjectManagement\Http\Resources\ClientResource;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Services\ClientService;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientApiController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'tier']);
        $clients = $this->clientService->getPaginatedClients($filters);

        return ApiResponse::paginated(ClientResource::collection($clients));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->clientService->createClient($request->validated());

        return ApiResponse::success(
            new ClientResource($client->load('manager')), 
            'Client created.', 
            [], 
            201
        );
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        if (!$request->user()->hasPermission('view', 'client')) {
            abort(403, 'Unauthorized action.');
        }

        $client->load(['manager']);
        $projects = $client->projects()->orderByDesc('updated_at')->paginate(50);

        return ApiResponse::success(
            [
                'client' => new ClientResource($client),
                'projects' => \App\Modules\ProjectManagement\Http\Resources\ProjectResource::collection($projects)->response()->getData(true),
            ],
            'Success'
        );
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client = $this->clientService->updateClient($client, $request->validated());

        return ApiResponse::success(new ClientResource($client), 'Client updated.');
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        if (!$request->user()->hasPermission('delete', 'client')) {
            abort(403, 'Unauthorized action.');
        }

        $this->clientService->deleteClient($client);
        return ApiResponse::success(null, 'Client archived.');
    }
}
