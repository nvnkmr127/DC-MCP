<?php

namespace App\Modules\MCP\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\MCP\Adapters\CustomMcpAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Jobs\SyncMcpProviderJob;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Modules\Auth\Models\User;

class McpConnectionApiController extends Controller
{
    /** Built-in providers that have dedicated adapters. */
    private const BUILTIN_PROVIDERS = [
        'gmail', 'google_calendar', 'notion', 'zoho_cliq', 'meta_ads', 'make',
    ];

    public function index(Request $request): JsonResponse
    {
        $connections = McpConnection::all()->map(function ($c) {
            return collect($c->toArray())
                ->except(['credentials'])
                ->put('is_expired', $c->isExpired())
                ->put('is_custom', !in_array($c->provider, self::BUILTIN_PROVIDERS));
        });

        return ApiResponse::success($connections);
    }

    public function getCatalogue(Request $request): JsonResponse
    {
        $catalogue = [];
        $providersToInclude = array_merge(self::BUILTIN_PROVIDERS, ['custom']);

        foreach ($providersToInclude as $provider) {
            try {
                $adapter = $this->resolveAdapter($provider);
                $metadata = $adapter->getCatalogueMetadata();
                
                $catalogue[] = [
                    'provider' => $provider,
                    'display_name' => $metadata['display_name'] ?? $provider,
                    'description' => $metadata['description'] ?? '',
                    'logo_url' => $metadata['logo_url'] ?? null,
                    'setup_guide_url' => $metadata['setup_guide_url'] ?? null,
                    'api_version' => $adapter->getApiVersion(),
                    'capabilities' => $adapter->getCapabilities(),
                    'required_scopes' => method_exists($adapter, 'getAvailableScopes') ? $adapter->getAvailableScopes() : [],
                    'data_permissions' => method_exists($adapter, 'getDataPermissions') ? $adapter->getDataPermissions() : ['read' => [], 'write' => []],
                    'is_deprecated' => $metadata['is_deprecated'] ?? false,
                    'deprecation_message' => $metadata['deprecation_message'] ?? null,
                ];
            } catch (\Exception $e) {
                // Skip if adapter fails to load
                continue;
            }
        }

        return ApiResponse::success($catalogue);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:100'],
            'name'     => ['required', 'string', 'max:255'],
            'scopes'   => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],

            // Credentials — all optional at the top level; specific providers
            // may require certain keys, validated via the test-connection flow.
            'credentials'               => ['nullable', 'array'],
        ]);

        $isCustom = !in_array($data['provider'], self::BUILTIN_PROVIDERS);
        
        // Duplicate connection check by Name (instead of provider)
        $existingNameQuery = McpConnection::where('organization_id', $request->user()->organization_id)
            ->where('name', $data['name']);
            
        if ($isCustom) {
            $existingNameQuery->where('settings->base_url', $data['settings']['base_url'] ?? null);
        }
        
        if ($existingNameQuery->exists()) {
            return ApiResponse::error('A connection with this name already exists in your organization.', [], 422);
        }

        // For custom providers the settings.base_url is mandatory
        if ($isCustom && empty($data['settings']['base_url'])) {
            return ApiResponse::error(
                'settings.base_url is required for custom providers.',
                ['settings.base_url' => ['This field is required for custom providers.']],
                422
            );
        }

        // Validate linked_project_id
        if (!empty($data['settings']['linked_project_id'])) {
            $linkedProjectExists = \App\Modules\ProjectManagement\Models\Project::where('id', $data['settings']['linked_project_id'])
                ->where('organization_id', $request->user()->organization_id)
                ->exists();
                
            if (!$linkedProjectExists) {
                return ApiResponse::error(
                    'Invalid linked_project_id.',
                    ['settings.linked_project_id' => ['The specified project does not exist or belongs to another organization.']],
                    422
                );
            }
        }

        // Credential validation
        if (!empty($data['credentials'])) {
            $adapter = $this->resolveAdapter($data['provider']);
            $adapter->validateCredentialsFormat($data['credentials']);
        }

        if (!$isCustom) {
            $adapter = $this->resolveAdapter($data['provider']);
            $requiredScopes = $adapter->getAvailableScopes();
            $providedScopes = $data['scopes'] ?? [];
            
            if (!empty($requiredScopes)) {
                $missingScopes = array_diff($requiredScopes, $providedScopes);
                if (!empty($missingScopes)) {
                    return ApiResponse::error(
                        'Insufficient scopes provided.',
                        ['scopes' => ['Missing required scopes: ' . implode(', ', $missingScopes)]],
                        422
                    );
                }
            }
        }

        $creds = $this->encryptCredentials($data['credentials'] ?? []);
        $settings = $data['settings'] ?? [];
        
        if (!$isCustom) {
            $adapter = $this->resolveAdapter($data['provider']);
            $settings['api_version'] = $adapter->getApiVersion();
        }

        $connection = McpConnection::create([
            'organization_id' => $request->user()->organization_id,
            'user_id'         => $request->user()->id,
            'provider'        => $data['provider'],
            'name'            => $data['name'],
            'status'          => \App\Modules\MCP\Enums\ConnectionStatus::PENDING_VERIFICATION->value,
            'credentials'     => $creds,
            'scopes'          => $data['scopes'] ?? [],
            'settings'        => $settings,
        ]);

        return ApiResponse::success(
            collect($connection->toArray())->except(['credentials'])
                ->put('is_custom', $isCustom),
            'Connection created successfully.',
            [],
            201
        );
    }

    public function show(McpConnection $mcpConnection): JsonResponse
    {
        return ApiResponse::success(
            collect($mcpConnection->toArray())
                ->except(['credentials'])
                ->put('is_expired', $mcpConnection->isExpired())
                ->put('is_custom', !in_array($mcpConnection->provider, self::BUILTIN_PROVIDERS))
        );
    }

    public function update(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'scopes'   => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
            'status'   => ['sometimes', 'string', \Illuminate\Validation\Rule::enum(\App\Modules\MCP\Enums\ConnectionStatus::class)],
            'user_id'  => ['sometimes', 'integer'],
            'credentials'               => ['sometimes', 'array'],
        ]);

        // Validate user_id if present (ownership transfer)
        if (!empty($data['user_id'])) {
            $userExists = User::where('id', $data['user_id'])
                ->where('organization_id', $request->user()->organization_id)
                ->exists();

            if (!$userExists) {
                return ApiResponse::error(
                    'Invalid user_id.',
                    ['user_id' => ['The specified user does not exist or belongs to another organization.']],
                    422
                );
            }
        }

        // Validate linked_project_id if present
        if (!empty($data['settings']['linked_project_id'])) {
            $linkedProjectExists = \App\Modules\ProjectManagement\Models\Project::where('id', $data['settings']['linked_project_id'])
                ->where('organization_id', $request->user()->organization_id)
                ->exists();
                
            if (!$linkedProjectExists) {
                return ApiResponse::error(
                    'Invalid linked_project_id.',
                    ['settings.linked_project_id' => ['The specified project does not exist or belongs to another organization.']],
                    422
                );
            }
        }

        if (isset($data['scopes']) && !in_array($mcpConnection->provider, self::BUILTIN_PROVIDERS) === false) {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $requiredScopes = $adapter->getAvailableScopes();
            
            if (!empty($requiredScopes)) {
                $missingScopes = array_diff($requiredScopes, $data['scopes']);
                if (!empty($missingScopes)) {
                    return ApiResponse::error(
                        'Insufficient scopes provided.',
                        ['scopes' => ['Missing required scopes: ' . implode(', ', $missingScopes)]],
                        422
                    );
                }
            }
        }

        if ($request->has('credentials')) {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $adapter->validateCredentialsFormat($data['credentials'] ?? []);
            
            // Re-encrypt the credentials
            $data['credentials'] = $this->encryptCredentials($data['credentials'] ?? []);
            
            // Set status to PENDING_VERIFICATION since credentials have changed
            $data['status'] = \App\Modules\MCP\Enums\ConnectionStatus::PENDING_VERIFICATION->value;
        }
        
        if (!in_array($mcpConnection->provider, self::BUILTIN_PROVIDERS) === false) {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $settings = $data['settings'] ?? $mcpConnection->settings ?? [];
            $settings['api_version'] = $adapter->getApiVersion();
            $data['settings'] = $settings;
        }

        $mcpConnection->update($data);

        return ApiResponse::success(
            collect($mcpConnection->fresh()->toArray())->except(['credentials']),
            'Connection updated.'
        );
    }

    public function destroy(McpConnection $mcpConnection): JsonResponse
    {
        try {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $raw = $mcpConnection->credentials ?? [];
            
            // Check if nested environments exist
            $environmentsToRevoke = [];
            if (isset($raw['production']) || isset($raw['staging'])) {
                foreach (['production', 'staging'] as $env) {
                    if (isset($raw[$env])) {
                        $environmentsToRevoke[] = $this->decryptConnectionCredentials($mcpConnection, $env);
                    }
                }
            } else {
                $environmentsToRevoke[] = $this->decryptConnectionCredentials($mcpConnection);
            }

            foreach ($environmentsToRevoke as $credentials) {
                if (!empty($credentials)) {
                    $adapter->revokeCredentials($credentials);
                }
            }
        } catch (\Exception $e) {
            // Log but don't prevent deletion
            \Illuminate\Support\Facades\Log::warning("Failed to revoke credentials on deletion: " . $e->getMessage());
        }

        $mcpConnection->delete();
        return ApiResponse::success(null, 'Connection deleted.');
    }

    public function sync(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        $options = [
            'environment' => $request->query('environment', 'production'),
            'full_resync' => $request->boolean('full_resync', false),
            'dry_run'     => $request->boolean('dry_run', false),
            'date_range'  => $request->input('date_range'),
            'records'     => $request->input('records'),
        ];
        
        \App\Modules\MCP\Jobs\SyncMcpProviderJob::dispatch($mcpConnection, $options);
        
        return ApiResponse::success(null, 'Sync queued.', [], 202);
    }

    public function pause(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        $mcpConnection->pauseSync();
        return ApiResponse::success(null, 'Sync paused.');
    }

    public function resume(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        $mcpConnection->resumeSync();
        return ApiResponse::success(null, 'Sync resumed.');
    }

    public function getSyncTrending(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        // Get the last 30 days of sync data for this connection
        $since = now()->subDays(30);

        $logs = \App\Modules\MCP\Models\McpSyncLog::where('mcp_connection_id', $mcpConnection->id)
            ->where('created_at', '>=', $since)
            ->where('status', 'success')
            ->selectRaw('DATE(created_at) as date, SUM(records_processed) as total_processed, SUM(bytes_transferred) as total_bytes')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return ApiResponse::success($logs);
    }

    public function syncPreview(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        if (in_array($mcpConnection->provider, self::BUILTIN_PROVIDERS) === false) {
            return ApiResponse::error('Sync preview is not supported for custom providers.', 400);
        }

        try {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $environment = $request->query('environment', 'production');
            $credentials = $this->decryptConnectionCredentials($mcpConnection, $environment);
            
            $preview = $adapter->syncPreview($credentials);
            return ApiResponse::success($preview);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate sync preview: ' . $e->getMessage(), 500);
        }
    }

    public function clone(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        // Decrypt the existing credentials
        $decryptedCredentials = $this->decryptConnectionCredentials($mcpConnection);
        
        // Re-encrypt them for the new record
        $reEncryptedCredentials = $this->encryptCredentials($decryptedCredentials);

        $newConnection = McpConnection::create([
            'organization_id' => $mcpConnection->organization_id,
            'user_id'         => $request->user()->id,
            'provider'        => $mcpConnection->provider,
            'name'            => $mcpConnection->name . ' (Copy)',
            'status'          => \App\Modules\MCP\Enums\ConnectionStatus::PENDING_VERIFICATION->value,
            'credentials'     => $reEncryptedCredentials,
            'scopes'          => $mcpConnection->scopes,
            'settings'        => $mcpConnection->settings,
        ]);

        return ApiResponse::success(
            collect($newConnection->toArray())->except(['credentials'])
                ->put('is_custom', !in_array($newConnection->provider, self::BUILTIN_PROVIDERS)),
            'Connection cloned successfully.',
            [],
            201
        );
    }

    public function test(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        try {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $environment = $request->query('environment', 'production');

            // Pass decrypted credentials + settings so adapters can connect
            $credentials = $this->decryptConnectionCredentials($mcpConnection, $environment);
            $credentials['_settings'] = $mcpConnection->settings ?? [];

            $result = $adapter->testConnection($credentials);

            return ApiResponse::success([
                'success'     => $result->isConnected,
                'message'     => $result->isConnected
                    ? 'Connection is working.'
                    : ($result->errorMessage ?? 'Connection test failed.'),
                'diagnostics' => $result->diagnostics,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Connection test failed: ' . $e->getMessage(), [], 422);
        }
    }

    public function getOAuthUrl(Request $request, string $provider): JsonResponse
    {
        $redirectUri = $request->query('redirect_uri');
        $scopes = $request->query('scopes', []);

        if (empty($redirectUri)) {
            return ApiResponse::error('redirect_uri is required', [], 422);
        }

        try {
            $adapter = $this->resolveAdapter($provider);
            $scopesArray = is_array($scopes) ? $scopes : explode(',', $scopes);
            
            $state = \Illuminate\Support\Str::random(40);
            $codeVerifier = \Illuminate\Support\Str::random(128);
            
            \Illuminate\Support\Facades\Cache::put("oauth_state_{$state}", $codeVerifier, now()->addMinutes(15));
            
            $url = $adapter->getOAuthUrl($redirectUri, $scopesArray, $state, $codeVerifier);

            return ApiResponse::success(['url' => $url]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate OAuth URL: ' . $e->getMessage(), [], 400);
        }
    }

    public function oauthExchange(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
        ]);

        $codeVerifier = \Illuminate\Support\Facades\Cache::pull("oauth_state_{$data['state']}");

        if (!$codeVerifier) {
            return ApiResponse::error('Invalid or expired state parameter (CSRF mismatch).', [], 403);
        }

        try {
            $adapter = $this->resolveAdapter($provider);
            $credentials = $adapter->exchangeAuthCode($data['code'], $codeVerifier, $data['redirect_uri']);
            
            return ApiResponse::success(['credentials' => $credentials]);
        } catch (\Exception $e) {
            return ApiResponse::error('OAuth exchange failed: ' . $e->getMessage(), [], 400);
        }
    }

    public function testScopes(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        try {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $environment = $request->query('environment', 'production');
            $credentials = $this->decryptConnectionCredentials($mcpConnection, $environment);
            $scopes = $mcpConnection->scopes ?? [];

            if (empty($scopes)) {
                return ApiResponse::success(['results' => []], 'No scopes to test.');
            }

            $results = $adapter->testScopes($credentials, $scopes);

            return ApiResponse::success(['results' => $results]);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to test scopes: ' . $e->getMessage(), [], 422);
        }
    }

    public function webhook(Request $request, string $provider, string $connectionId): JsonResponse
    {
        $connection = McpConnection::where('provider', $provider)
            ->where('id', $connectionId)
            ->first();

        if (!$connection) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // IP Allowlisting Enforcement
        $allowedIps = $connection->settings['allowed_ips'] ?? [];
        if (!empty($allowedIps) && is_array($allowedIps)) {
            if (!\Symfony\Component\HttpFoundation\IpUtils::checkIp($request->ip(), $allowedIps)) {
                return response()->json(['message' => 'IP address not allowed.'], 403);
            }
        }

        try {
            $adapter = $this->resolveAdapter($provider);
            
            // Replay Attack Prevention (5 minute window)
            $timestamp = $adapter->extractWebhookTimestamp($request);
            if ($timestamp !== null && abs(time() - $timestamp) > 300) {
                return response()->json(['message' => 'Webhook payload is too old (replay attack prevention).'], 403);
            }

            // Idempotency Key Tracking
            $idempotencyKey = $adapter->extractWebhookIdempotencyKey($request);
            if ($idempotencyKey !== null) {
                $exists = \Illuminate\Support\Facades\DB::table('mcp_webhook_events')
                    ->where('mcp_connection_id', $connection->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->exists();

                if ($exists) {
                    // Already processed, return 200 OK immediately to acknowledge retry without processing
                    return response()->json(['message' => 'Webhook already processed (idempotency key matched).', 'status' => 'success'], 200);
                }
            }

            // Log event to database
            $eventId = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\DB::table('mcp_webhook_events')->insert([
                'id' => $eventId,
                'mcp_connection_id' => $connection->id,
                'provider' => $provider,
                'event_type' => $request->input('type') ?? 'webhook_received',
                'idempotency_key' => $idempotencyKey,
                'payload' => json_encode($request->all()),
                'status' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result  = $adapter->handleWebhook($request);

            event(new \App\Modules\MCP\Events\McpWebhookReceived($connection, $result, $request->all(), $eventId));

            return response()->json([
                'message' => 'Webhook processed.',
                'status'  => $result->status,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }
    }

    public function replayWebhook(Request $request, string $eventId): JsonResponse
    {
        $event = \Illuminate\Support\Facades\DB::table('mcp_webhook_events')
            ->where('id', $eventId)
            ->first();

        if (!$event) {
            return response()->json(['message' => 'Webhook event not found.'], 404);
        }

        $connection = McpConnection::find($event->mcp_connection_id);
        if (!$connection) {
            return response()->json(['message' => 'Associated connection not found.'], 404);
        }

        \Illuminate\Support\Facades\DB::table('mcp_webhook_events')
            ->where('id', $eventId)
            ->update([
                'status' => 'processing',
                'updated_at' => now(),
            ]);

        $payload = json_decode($event->payload, true) ?? [];
        
        try {
            $adapter = $this->resolveAdapter($event->provider);
            
            // Create a mock request with the original payload
            $mockRequest = Request::create('/webhook', 'POST', $payload);
            $mockRequest->headers->set('Content-Type', 'application/json');
            
            $result = $adapter->handleWebhook($mockRequest);

            event(new \App\Modules\MCP\Events\McpWebhookReceived($connection, $result, $payload, $eventId));

            return response()->json([
                'message' => 'Webhook replay initiated.',
                'status'  => 'processing',
            ], 202);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::table('mcp_webhook_events')
                ->where('id', $eventId)
                ->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);
            return response()->json(['message' => 'Webhook replay failed to initiate.'], 500);
        }
    }

    /** Lists both built-in and any registered custom providers for the org. */
    public function providers(Request $request): JsonResponse
    {
        $usedProviders = McpConnection::where('organization_id', $request->user()->organization_id)
            ->select('provider')
            ->distinct()
            ->pluck('provider')
            ->toArray();

        return ApiResponse::success([
            'builtin' => self::BUILTIN_PROVIDERS,
            'custom'  => array_values(array_filter($usedProviders, fn ($p) => !in_array($p, self::BUILTIN_PROVIDERS))),
        ]);
    }

    public function getProviderStatus(Request $request, string $provider): JsonResponse
    {
        try {
            $adapter = $this->resolveAdapter($provider);
            $status = $adapter->getExternalStatus();
            
            if ($status === null) {
                return ApiResponse::success([
                    'status' => 'unknown',
                    'description' => 'Real-time status tracking is not supported for this provider.'
                ]);
            }
            
            return ApiResponse::success($status);
        } catch (\Exception $e) {
            return ApiResponse::success([
                'status' => 'unknown',
                'description' => 'Unable to fetch provider status at this time.'
            ]);
        }
    }

    public function getDiagnostics(Request $request, string $provider): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $timeframe = $request->query('timeframe', '24h');
        
        $hours = 24;
        if ($timeframe === '7d') {
            $hours = 168;
        } elseif ($timeframe === '30d') {
            $hours = 720;
        }

        $since = now()->subHours($hours);

        $connectionIds = McpConnection::where('organization_id', $organizationId)
            ->where('provider', $provider)
            ->pluck('id');

        if ($connectionIds->isEmpty()) {
            return ApiResponse::success([
                'error_rate_percent' => 0,
                'avg_latency_ms'     => 0,
                'total_syncs'        => 0,
                'failed_syncs'       => 0,
                'records_processed'  => 0,
                'bytes_transferred'  => 0,
                'timeframe'          => $timeframe,
            ]);
        }

        $logs = \App\Modules\MCP\Models\McpSyncLog::whereIn('mcp_connection_id', $connectionIds)
            ->where('created_at', '>=', $since)
            ->get();

        $totalSyncs = $logs->count();
        if ($totalSyncs === 0) {
            return ApiResponse::success([
                'error_rate_percent' => 0,
                'avg_latency_ms'     => 0,
                'total_syncs'        => 0,
                'failed_syncs'       => 0,
                'records_processed'  => 0,
                'bytes_transferred'  => 0,
                'timeframe'          => $timeframe,
            ]);
        }

        $failedSyncs = $logs->where('status', 'failed')->count();
        $errorRate = round(($failedSyncs / $totalSyncs) * 100, 2);
        
        $successfulLogs = $logs->where('status', 'success');
        $avgLatency = $successfulLogs->avg('duration_ms') ?? 0;
        $totalRecords = $successfulLogs->sum('records_processed');
        $totalBytes = $successfulLogs->sum('bytes_transferred');

        return ApiResponse::success([
            'error_rate_percent' => $errorRate,
            'avg_latency_ms'     => round($avgLatency),
            'total_syncs'        => $totalSyncs,
            'failed_syncs'       => $failedSyncs,
            'records_processed'  => $totalRecords,
            'bytes_transferred'  => $totalBytes,
            'timeframe'          => $timeframe,
        ]);
    }

    public function getRateLimits(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        try {
            $adapter = $this->resolveAdapter($mcpConnection->provider);
            $environment = $request->query('environment', 'production');
            $credentials = $this->decryptConnectionCredentials($mcpConnection, $environment);

            $rateLimits = $adapter->getRateLimitStatus($credentials);

            if ($rateLimits === null) {
                return ApiResponse::success([
                    'supported' => false,
                    'message' => 'Rate limit visibility is not supported for this provider.'
                ]);
            }

            return ApiResponse::success(array_merge(['supported' => true], $rateLimits));
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch rate limits: ' . $e->getMessage(), [], 422);
        }
    }

    public function export(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $connections = McpConnection::where('organization_id', $organizationId)->get();

        $exported = [];
        foreach ($connections as $connection) {
            $rawCredentials = $connection->credentials ?? [];
            $decryptedCredentials = [];

            // Export all nested environments or flat credentials
            if (isset($rawCredentials['production']) || isset($rawCredentials['staging'])) {
                foreach ($rawCredentials as $env => $block) {
                    $decryptedCredentials[$env] = $this->decryptConnectionCredentials($connection, $env);
                }
            } else {
                $decryptedCredentials = $this->decryptConnectionCredentials($connection);
            }

            $exported[] = [
                'provider'    => $connection->provider,
                'name'        => $connection->name,
                'status'      => $connection->status,
                'scopes'      => $connection->scopes,
                'settings'    => $connection->settings,
                'credentials' => $decryptedCredentials,
            ];
        }

        return response()->json([
            'version' => '1.0',
            'connections' => $exported,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'connections' => ['required', 'array'],
            'connections.*.provider' => ['required', 'string'],
            'connections.*.name' => ['required', 'string'],
            'connections.*.status' => ['nullable', 'string'],
            'connections.*.scopes' => ['nullable', 'array'],
            'connections.*.settings' => ['nullable', 'array'],
            'connections.*.credentials' => ['nullable', 'array'],
        ]);

        $organizationId = $request->user()->organization_id;
        $userId = $request->user()->id;
        $importedCount = 0;

        foreach ($data['connections'] as $connData) {
            // Check for duplicates by name and provider
            $existing = McpConnection::where('organization_id', $organizationId)
                ->where('name', $connData['name'])
                ->where('provider', $connData['provider'])
                ->first();

            if ($existing) {
                continue; // Skip existing
            }

            $credentials = $connData['credentials'] ?? [];
            $encryptedCredentials = $this->encryptCredentials($credentials);

            McpConnection::create([
                'organization_id' => $organizationId,
                'user_id'         => $userId,
                'provider'        => $connData['provider'],
                'name'            => $connData['name'],
                'status'          => $connData['status'] ?? \App\Modules\MCP\Enums\ConnectionStatus::PENDING_VERIFICATION->value,
                'scopes'          => $connData['scopes'] ?? [],
                'settings'        => $connData['settings'] ?? [],
                'credentials'     => $encryptedCredentials,
            ]);
            $importedCount++;
        }

        return ApiResponse::success([
            'imported' => $importedCount,
            'skipped' => count($data['connections']) - $importedCount
        ], "Successfully imported {$importedCount} connections.");
    }

    private function resolveAdapter(string $provider): \App\Modules\MCP\Contracts\MCPAdapter
    {
        return match ($provider) {
            'gmail'           => app(\App\Modules\MCP\Adapters\GmailAdapter::class),
            'google_calendar' => app(\App\Modules\MCP\Adapters\GoogleCalendarAdapter::class),
            'notion'          => app(\App\Modules\MCP\Adapters\NotionAdapter::class),
            'zoho_cliq'       => app(\App\Modules\MCP\Adapters\ZohoCliqAdapter::class),
            'meta_ads'        => app(\App\Modules\MCP\Adapters\MetaAdsAdapter::class),
            'make'            => app(\App\Modules\MCP\Adapters\MakeAdapter::class),
            default           => app(CustomMcpAdapter::class),
        };
    }

    private function decryptConnectionCredentials(McpConnection $connection, string $environment = 'production'): array
    {
        $raw = $connection->credentials ?? [];
        
        // If the credentials payload has nested environments (e.g., 'production', 'staging'), extract the requested one
        // If not, assume the root level is the credentials payload (backwards compatibility).
        if (isset($raw[$environment]) && is_array($raw[$environment])) {
            $raw = $raw[$environment];
        } elseif (isset($raw['production']) || isset($raw['staging'])) {
            // It has environments but the requested one is missing
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            if (is_string($value)) {
                try {
                    $out[$key] = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    $out[$key] = $value;
                }
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function encryptCredentials(array $credentials): array
    {
        $encryptable = ['access_token', 'refresh_token', 'api_key', 'username', 'password', 'service_account_json'];

        $encryptBlock = function(array $block) use ($encryptable) {
            $out = [];
            foreach ($block as $key => $value) {
                if (in_array($key, $encryptable) && is_string($value) && $value !== '') {
                    $out[$key] = Crypt::encryptString($value);
                } else {
                    $out[$key] = $value;
                }
            }
            // Stamp creation time if missing
            $out['created'] = $out['created'] ?? time();
            return $out;
        };

        // Check if credentials are nested by environment
        if (isset($credentials['production']) || isset($credentials['staging'])) {
            $out = [];
            foreach ($credentials as $env => $block) {
                if (is_array($block)) {
                    $out[$env] = $encryptBlock($block);
                } else {
                    $out[$env] = $block;
                }
            }
            return $out;
        }

        // Flat credentials
        return $encryptBlock($credentials);
    }
}
