<?php

namespace App\Modules\MCP\Contracts;

use App\Modules\MCP\DataObjects\ConnectionTestResult;
use App\Modules\MCP\DataObjects\SyncResult;
use App\Modules\MCP\DataObjects\WebhookResult;
use Illuminate\Http\Request;

interface MCPAdapter
{
    /**
     * Authenticate or prepare credentials for the external API.
     *
     * @param array $credentials
     * @return bool
     */
    public function authenticate(array $credentials): bool;

    /**
     * Pull data from the external source to local database.
     *
     * @param string $connectionId
     * @return SyncResult
     */
    public function sync(string $connectionId): SyncResult;

    /**
     * Push local changes to the external system.
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    public function push(string $connectionId, array $data): SyncResult;

    /**
     * Handle incoming webhooks from the external service.
     *
     * @param Request $request
     * @return WebhookResult
     */
    public function handleWebhook(Request $request): WebhookResult;

    /**
     * Test the connection to the external API using the provided credentials.
     *
     * @param array $credentials
     * @return ConnectionTestResult
     */
    public function testConnection(array $credentials): ConnectionTestResult;

    /**
     * Get the available scopes or permissions needed by this adapter.
     *
     * @return array
     */
    public function getAvailableScopes(): array;
}
