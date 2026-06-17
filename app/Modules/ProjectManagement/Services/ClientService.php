<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Client;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientService
{
    /**
     * Get paginated list of clients.
     */
    public function getPaginatedClients(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Client::with('manager')
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['tier']), fn ($q) => $q->where('tier', $filters['tier']))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a new client.
     */
    public function createClient(array $data): Client
    {
        $data['status'] = $data['status'] ?? 'active';
        
        return Client::create($data);
    }

    /**
     * Update an existing client.
     */
    public function updateClient(Client $client, array $data): Client
    {
        $client->update($data);
        return $client->fresh(['manager']);
    }

    /**
     * Archive/Delete a client.
     */
    public function deleteClient(Client $client): void
    {
        $client->delete();
    }
}
