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
     * Pre-save credential format validation to prevent malformed tokens.
     * Throws ValidationException if validation fails.
     *
     * @param array $credentials
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCredentialsFormat(array $credentials): void;

    public function getAvailableScopes(): array;

    /**
     * Get the OAuth authorization URL for the provider.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string $state
     * @param string $codeVerifier
     * @return string
     */
    public function getOAuthUrl(string $redirectUri, array $scopes = [], string $state = '', string $codeVerifier = ''): string;

    /**
     * Exchange OAuth code for credentials.
     *
     * @param string $code
     * @param string $codeVerifier
     * @param string $redirectUri
     * @return array Returns credential array
     */
    public function exchangeAuthCode(string $code, string $codeVerifier, string $redirectUri): array;

    /**
     * Revoke the provider credentials if supported.
     *
     * @param array $credentials
     * @return void
     */
    public function revokeCredentials(array $credentials): void;

    /**
     * Test individual scopes for the connection.
     *
     * @param array $credentials
     * @param array $scopes
     * @return array Returns a map of scope => boolean
     */
    public function testScopes(array $credentials, array $scopes): array;

    /**
     * Get external provider status if supported.
     *
     * @return array|null Returns ['status' => 'operational|degraded|outage', 'description' => '...'] or null
     */
    public function getExternalStatus(): ?array;

    /**
     * Get the API capabilities supported by this adapter.
     *
     * @return array List of capability strings (e.g., ['read_emails', 'send_emails'])
     */
    public function getCapabilities(): array;

    /**
     * Get the active API version this adapter targets.
     *
     * @return string
     */
    public function getApiVersion(): string;

    /**
     * Get rich metadata for the provider catalogue UI.
     * Includes is_deprecated and deprecation_message flags.
     *
     * @return array
     */
    public function getCatalogueMetadata(): array;

    /**
     * Get real-time rate limit visibility for this connection.
     *
     * @param array $credentials
     * @return array|null ['limit' => int, 'remaining' => int, 'reset_at' => int|string] or null if unsupported
     */
    public function getRateLimitStatus(array $credentials): ?array;

    /**
     * Get human-readable list of data types the provider reads or writes.
     *
     * @return array ['read' => ['emails'], 'write' => ['drafts']]
     */
    public function getDataPermissions(): array;

    /**
     * Preview what data will be synced without actually syncing it.
     *
     * @param array $credentials
     * @param array $options Additional options like page size or specific endpoints
     * @return array ['supported' => true, 'records_to_process' => int, 'summary' => string]
     */
    public function syncPreview(array $credentials, array $options = []): array;
}
