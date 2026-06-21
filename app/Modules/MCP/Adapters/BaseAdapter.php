<?php

namespace App\Modules\MCP\Adapters;

use App\Modules\MCP\Contracts\MCPAdapter;
use App\Modules\MCP\DataObjects\SyncResult;
use App\Modules\MCP\DataObjects\WebhookResult;
use App\Modules\MCP\DataObjects\ConnectionTestResult;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseAdapter implements MCPAdapter
{
    protected Client $client;
    protected int $maxRetries = 3;
    protected int $baseDelayMs = 1000;

    /**
     * Get the provider identifier (e.g. google_calendar, notion).
     *
     * @return string
     */
    abstract protected function getProviderName(): string;

    /**
     * Get the standardized Guzzle HandlerStack with all resilience middlewares.
     *
     * @return HandlerStack
     */
    protected function getGuzzleHandlerStack(): HandlerStack
    {
        $stack = HandlerStack::create();
        $stack->push($this->createCircuitBreakerMiddleware(), 'circuit_breaker');
        $stack->push($this->createRetryMiddleware(), 'retry');
        $stack->push($this->createRateLimitThrottlingMiddleware(), 'rate_limit_throttle');
        return $stack;
    }

    /**
     * Initialize Guzzle client with credentials/base URI/headers.
     *
     * @param string $baseUri
     * @param array $headers
     * @param array $clientConfig
     * @return void
     */
    protected function setupClient(string $baseUri, array $headers = [], array $clientConfig = []): void
    {
        $config = array_merge([
            'base_uri' => $baseUri,
            'headers'  => $headers,
            'handler'  => $this->getGuzzleHandlerStack(),
            'timeout'  => 30.0,
        ], $clientConfig);

        $this->client = new Client($config);
    }

    /**
     * Decrypt values in a credentials array.
     *
     * @param array $credentials
     * @return array
     */
    protected function decryptCredentials(#[\SensitiveParameter] array $credentials): array
    {
        $decrypted = [];
        $decryptedKeys = [];

        foreach ($credentials as $key => $value) {
            try {
                $decrypted[$key] = Crypt::decryptString($value);
                $decryptedKeys[] = $key;
            } catch (\Exception $e) {
                // If it fails to decrypt, assume it's either not encrypted or treat as plain
                $decrypted[$key] = $value;
            }
        }

        // Audit log the decryption event
        if (!empty($decryptedKeys)) {
            // Attempt to trace the caller to provide better context
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $caller = $trace[2]['function'] ?? ($trace[1]['function'] ?? 'unknown');
            $class  = $trace[2]['class'] ?? ($trace[1]['class'] ?? 'unknown');

            \Illuminate\Support\Facades\Log::channel('audit')->info('Credentials decrypted', [
                'provider'       => method_exists($this, 'getProviderName') ? $this->getProviderName() : 'unknown',
                'caller_class'   => $class,
                'caller_method'  => $caller,
                'decrypted_keys' => $decryptedKeys,
                'user_id'        => auth()->check() ? auth()->id() : 'system',
                'ip_address'     => request()->ip() ?? 'cli',
                'timestamp'      => now()->toIso8601String(),
            ]);
        }

        return $decrypted;
    }

    /**
     * Encrypt values in a credentials array before saving.
     *
     * @param array $credentials
     * @return array
     */
    protected function encryptCredentials(#[SensitiveParameter] array $credentials): array
    {
        $encrypted = [];
        foreach ($credentials as $key => $value) {
            $encrypted[$key] = Crypt::encryptString($value);
        }
        return $encrypted;
    }

    /**
     * Log a sync operation into mcp_sync_logs.
     *
     * @param string $connectionId
     * @param string $direction
     * @param string $entityType
     * @param string|null $entityId
     * @param string $status
     * @param int $processed
     * @param int $failed
     * @param array|null $payload
     * @param string|null $errorMessage
     * @param int $durationMs
     * @return void
     */
    protected function logSync(
        string $connectionId,
        string $direction,
        string $entityType,
        ?string $entityId,
        string $status,
        int $processed = 0,
        int $failed = 0,
        ?array $payload = null,
        ?string $errorMessage = null,
        int $durationMs = 0,
        ?string $userId = null,
        ?string $idempotencyKey = null
    ): void {
        \App\Jobs\LogMcpConnectionEvent::dispatch(
            $connectionId,
            $direction,
            $entityType,
            $entityId,
            $status,
            $processed,
            $failed,
            $payload,
            $errorMessage,
            $durationMs,
            $userId,
            $idempotencyKey
        );
    }

    /**
     * Create retry middleware with exponential backoff & rate limit handling.
     *
     * @return callable
     */
    protected function createRetryMiddleware(): callable
    {
        return Middleware::retry(
            // Decider function
            function (
                int $retries,
                RequestInterface $request,
                ?ResponseInterface $response,
                ?RequestException $exception
            ) {
                // Limit retries
                if ($retries >= $this->maxRetries) {
                    return false;
                }

                // Retry on server errors or too many requests (rate limits)
                if ($response) {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 429 || $statusCode >= 500) {
                        return true;
                    }
                }

                // Retry on network errors
                if ($exception) {
                    return true;
                }

                return false;
            },
            // Delay function
            function (int $retries, ?ResponseInterface $response) {
                // If the response contains a Retry-After header, respect it
                if ($response && $response->hasHeader('Retry-After')) {
                    $retryAfter = $response->getHeaderLine('Retry-After');
                    if (is_numeric($retryAfter)) {
                        return (int) $retryAfter * 1000;
                    }
                    // If it's a date string, calculate delay
                    $date = strtotime($retryAfter);
                    if ($date !== false) {
                        return max(0, ($date - time()) * 1000);
                    }
                }

                // Otherwise, calculate exponential delay
                return (int) (pow(2, $retries) * $this->baseDelayMs);
            }
        );
    }

    /**
     * Create rate limit throttling middleware.
     * Proactively delays the next request if we are close to the provider's rate limit.
     *
     * @return callable
     */
    protected function createRateLimitThrottlingMiddleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $provider = $this->getProviderName();
                $cacheKey = "mcp_rate_limit_throttle_{$provider}";
                
                // 1. BEFORE request: check if we need to throttle based on the previous response
                $throttleUntil = \Illuminate\Support\Facades\Cache::get($cacheKey);
                if ($throttleUntil && $throttleUntil > microtime(true)) {
                    $sleepTime = $throttleUntil - microtime(true);
                    if ($sleepTime > 0) {
                        usleep((int) ($sleepTime * 1000000));
                    }
                }

                // 2. Perform request
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($cacheKey) {
                        // 3. AFTER request: parse rate limit headers to dictate the next request's delay
                        if ($response->hasHeader('Retry-After')) {
                            $retryAfter = $response->getHeaderLine('Retry-After');
                            $delaySeconds = is_numeric($retryAfter) ? (float) $retryAfter : max(0, strtotime($retryAfter) - time());
                            if ($delaySeconds > 0) {
                                \Illuminate\Support\Facades\Cache::put($cacheKey, microtime(true) + $delaySeconds, ceil($delaySeconds));
                            }
                        } elseif ($response->hasHeader('X-RateLimit-Remaining')) {
                            $remaining = (int) $response->getHeaderLine('X-RateLimit-Remaining');
                            $reset = $response->hasHeader('X-RateLimit-Reset') ? (int) $response->getHeaderLine('X-RateLimit-Reset') : null;
                            
                            if ($remaining < 5 && $reset) {
                                // Close to limit, sleep until reset
                                $delaySeconds = max(0, $reset - time());
                                \Illuminate\Support\Facades\Cache::put($cacheKey, microtime(true) + $delaySeconds, ceil($delaySeconds));
                            } elseif ($remaining < 5) {
                                // Close to limit but no reset header, apply a default 2s backoff for the next request
                                \Illuminate\Support\Facades\Cache::put($cacheKey, microtime(true) + 2, 2);
                            }
                        }

                        return $response;
                    }
                );
            };
        };
    }

    /**
     * Create circuit breaker middleware.
     *
     * @return callable
     */
    protected function createCircuitBreakerMiddleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $provider = $this->getProviderName();
                $cacheKey = "mcp_circuit_breaker_{$provider}_failures";
                
                // Check if circuit is open
                if (\Illuminate\Support\Facades\Cache::get($cacheKey) >= 5) {
                    throw new \App\Modules\MCP\Exceptions\CircuitBreakerOpenException($provider);
                }

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($cacheKey) {
                        // Success resets the circuit
                        if ($response->getStatusCode() < 500 && $response->getStatusCode() !== 429) {
                            \Illuminate\Support\Facades\Cache::forget($cacheKey);
                        } else {
                            \Illuminate\Support\Facades\Cache::add($cacheKey, 0, now()->addMinutes(5));
                            \Illuminate\Support\Facades\Cache::increment($cacheKey);
                        }
                        
                        // Check provider quotas
                        if ($response->hasHeader('X-RateLimit-Remaining') && $response->hasHeader('X-RateLimit-Limit')) {
                            $remaining = (int) $response->getHeaderLine('X-RateLimit-Remaining');
                            $limit = (int) $response->getHeaderLine('X-RateLimit-Limit');
                            
                            if ($limit > 0 && ($remaining / $limit) <= 0.10) {
                                \Illuminate\Support\Facades\Log::warning(
                                    "Provider API quota approaching alert: Only {$remaining} requests remaining out of {$limit} for provider."
                                );
                            }
                        }

                        return $response;
                    },
                    function (\Exception $reason) use ($cacheKey) {
                        // Failure increments the circuit
                        \Illuminate\Support\Facades\Cache::add($cacheKey, 0, now()->addMinutes(5));
                        \Illuminate\Support\Facades\Cache::increment($cacheKey);
                        return \GuzzleHttp\Promise\Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    /**
     * Default credential format validation (noop).
     *
     * @param array $credentials
     * @return void
     */
    public function validateCredentialsFormat(#[SensitiveParameter] array $credentials): void
    {
        // Override in specific adapters
    }

    /**
     * Get the OAuth authorization URL for the provider.
     *
     * @param string $redirectUri
     * @param array $scopes
     * @param string $state
     * @param string $codeVerifier
     * @return string
     */
    public function getOAuthUrl(string $redirectUri, array $scopes = [], string $state = '', string $codeVerifier = ''): string
    {
        return '';
    }

    /**
     * Exchange OAuth code for credentials.
     *
     * @param string $code
     * @param string $codeVerifier
     * @param string $redirectUri
     * @return array
     * @throws \Exception
     */
    public function exchangeAuthCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        throw new \Exception('OAuth exchange is not supported for this provider.');
    }

    /**
     * Revoke the provider credentials if supported.
     *
     * @param array $credentials
     * @return void
     */
    public function revokeCredentials(#[SensitiveParameter] array $credentials): void
    {
        // NOOP by default
    }

    /**
     * Test individual scopes for the connection.
     *
     * @param array $credentials
     * @param array $scopes
     * @return array
     */
    public function testScopes(#[SensitiveParameter] array $credentials, array $scopes): array
    {
        $results = [];
        foreach ($scopes as $scope) {
            $results[$scope] = true; // Assume true if not explicitly tested
        }
        return $results;
    }

    /**
     * Get external provider status if supported.
     *
     * @return array|null
     */
    public function getExternalStatus(): ?array
    {
        return [
            'status' => 'unknown',
            'description' => 'Real-time status tracking is not supported for this provider.'
        ];
    }

    /**
     * Get the API capabilities supported by this adapter.
     *
     * @return array
     */
    public function getCapabilities(): array
    {
        return [];
    }

    /**
     * Get the active API version this adapter targets.
     *
     * @return string
     */
    public function getApiVersion(): string
    {
        return '1.0';
    }

    /**
     * Get rich metadata for the provider catalogue UI.
     *
     * @return array
     */
    public function getCatalogueMetadata(): array
    {
        $providerName = $this->getProviderName();
        return [
            'display_name'    => ucwords(str_replace('_', ' ', $providerName)),
            'description'     => 'Connects to ' . ucwords(str_replace('_', ' ', $providerName)) . ' API.',
            'logo_url'        => null,
            'setup_guide_url' => null,
            'is_deprecated'   => false,
            'deprecation_message' => null,
            'required_scopes' => method_exists($this, 'getAvailableScopes') ? $this->getAvailableScopes() : [],
            'supports_sandbox' => false,
        ];
    }

    /**
     * Get real-time rate limit visibility for this connection.
     *
     * @param array $credentials
     * @return array|null
     */
    public function getRateLimitStatus(#[SensitiveParameter] array $credentials): ?array
    {
        return null;
    }

    /**
     * Get human-readable list of data types the provider reads or writes.
     *
     * @return array
     */
    public function getDataPermissions(): array
    {
        return [
            'read' => [],
            'write' => []
        ];
    }

    /**
     * Push data to the provider with central controls (Rate Limiting, Auditing, Notifications).
     *
     * @param string $connectionId
     * @param array $data
     * @param array $options
     * @return SyncResult
     */
    public final function push(string $connectionId, array $data, array $options = []): SyncResult
    {
        $userId = $options['user_id'] ?? auth()->id();
        $idempotencyKey = $options['idempotency_key'] ?? null;

        // 0. Idempotency Check
        if ($idempotencyKey) {
            $existing = \Illuminate\Support\Facades\DB::table('mcp_sync_logs')
                ->where('mcp_connection_id', $connectionId)
                ->where('idempotency_key', $idempotencyKey)
                ->where('status', 'success')
                ->first();
                
            if ($existing) {
                return SyncResult::success(1, ['reason' => 'Skipped due to idempotency key match']);
            }
        }

        $rateLimitKey = "mcp_outbound_push:{$connectionId}";
        
        // 1. Rate Limiting: max 60 requests per minute
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
            $msg = 'Rate limit exceeded for outbound operation.';
            $this->logSync($connectionId, 'outbound', $data['entity_type'] ?? 'unknown', $data['entity_id'] ?? null, 'failed', 0, 1, $data, $msg, 0, $userId, $idempotencyKey);
            
            if ($userId) {
                app(\App\Modules\Notifications\Services\NotificationService::class)->sendNotification(
                    \App\Modules\Auth\Models\User::find($userId),
                    'outbound_action_failed',
                    'in_app',
                    'Outbound Action Failed',
                    $msg
                );
            }
            return SyncResult::failure($msg);
        }
        
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 60);

        try {
            // 2. Perform actual push
            $result = $this->performPush($connectionId, $data, $options);
            
            // 3. Audit Log per record for failures handled inside performPush or generic
            if (!$result->success && $result->message) {
                if ($userId) {
                    app(\App\Modules\Notifications\Services\NotificationService::class)->sendNotification(
                        \App\Modules\Auth\Models\User::find($userId),
                        'outbound_action_failed',
                        'in_app',
                        'Outbound Action Failed',
                        $result->message
                    );
                }
            }
            return $result;
        } catch (\Exception $e) {
            $this->logSync($connectionId, 'outbound', $data['entity_type'] ?? 'unknown', $data['entity_id'] ?? null, 'failed', 0, 1, $data, $e->getMessage(), 0, $userId, $idempotencyKey);
            
            if ($userId) {
                app(\App\Modules\Notifications\Services\NotificationService::class)->sendNotification(
                    \App\Modules\Auth\Models\User::find($userId),
                    'outbound_action_failed',
                    'in_app',
                    'Outbound Action Failed',
                    $e->getMessage()
                );
            }
            return SyncResult::failure($e->getMessage());
        }
    }

    abstract protected function performPush(string $connectionId, array $data, array $options = []): SyncResult;

    /**
     * Revert a previously successful outbound action.
     *
     * @param string $connectionId
     * @param string $logId
     * @param array $options
     * @return SyncResult
     */
    public final function revert(string $connectionId, string $logId, array $options = []): SyncResult
    {
        $log = \Illuminate\Support\Facades\DB::table('mcp_sync_logs')
            ->where('id', $logId)
            ->where('mcp_connection_id', $connectionId)
            ->first();

        if (!$log) {
            return SyncResult::failure('Log record not found.');
        }

        $metadata = json_decode($log->metadata, true);
        if (($metadata['direction'] ?? '') !== 'outbound') {
            return SyncResult::failure('Only outbound actions can be reverted.');
        }
        
        if ($log->status !== 'success' && $log->status !== 'partial_success') {
            return SyncResult::failure('Only successful actions can be reverted.');
        }

        $payload = $metadata['payload'] ?? [];
        
        try {
            return $this->performRevert($connectionId, $payload, $metadata, $options);
        } catch (\Exception $e) {
            return SyncResult::failure('Revert failed: ' . $e->getMessage());
        }
    }

    /**
     * Internal implementation of reverting data in the provider.
     * Default returns not supported.
     *
     * @param string $connectionId
     * @param array $data The original payload
     * @param array $logMetadata The metadata from the original successful log (might contain external_ids)
     * @param array $options
     * @return SyncResult
     */
    protected function performRevert(string $connectionId, array $data, array $logMetadata, array $options = []): SyncResult
    {
        return SyncResult::failure('Revert mechanism is not supported or implemented for this provider.');
    }

    /**
     * Preview what data will be synced without actually syncing it.
     *
     * @param array $credentials
     * @param array $options
     * @return array
     */
    public function syncPreview(#[SensitiveParameter] array $credentials, array $options = []): array
    {
        return [
            'supported' => false,
            'message' => 'Sync preview is not supported for this provider.'
        ];
    }

    /**
     * Extract the webhook timestamp from the request for replay attack prevention.
     *
     * @param Request $request
     * @return int|null
     */
    public function extractWebhookTimestamp(Request $request): ?int
    {
        // To be overridden by specific adapters
        return null;
    }

    /**
     * Extract an idempotency key from the webhook request to prevent duplicate processing.
     *
     * @param Request $request
     * @return string|null
     */
    public function extractWebhookIdempotencyKey(Request $request): ?string
    {
        // To be overridden by specific adapters
        return null;
    }

    /**
     * Fetch a sample record from the provider and test it against the proposed mappings.
     *
     * @param \App\Modules\MCP\Models\McpConnection $connection
     * @param array $credentials
     * @param array $proposedMappings
     * @return array Returns raw payload and transformed payload
     */
    public function previewMapping(\App\Modules\MCP\Models\McpConnection $connection, #[\SensitiveParameter] array $credentials, array $proposedMappings): array
    {
        throw new \Exception("Mapping preview is not implemented for this adapter.");
    }

    /**
     * Get available outbound actions (push operations) supported by this adapter.
     *
     * @return array
     */
    public function getOutboundActions(): array
    {
        return [];
    }

    /**
     * Validate an outbound payload against the action's defined schema.
     *
     * @param string $actionId
     * @param array $data
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    protected function validateOutboundPayload(string $actionId, array $data): void
    {
        $actions = $this->getOutboundActions();
        $action = collect($actions)->firstWhere('id', $actionId);
        
        if (!$action) {
            throw new \Exception("Action [{$actionId}] is not supported by this provider.");
        }
        
        $rules = $action['schema']['rules'] ?? [];
        if (!empty($rules)) {
            $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }
    }

    /**
     * Preview an outbound action payload without sending it.
     *
     * @param string $connectionId
     * @param string $actionId
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function previewOutboundAction(string $connectionId, string $actionId, array $data): array
    {
        throw new \Exception("Outbound action preview is not supported for this provider.");
    }

    /**
     * Check if the sync job should yield execution to prevent a hard timeout.
     * Leaves a 15-second buffer (e.g. timeout=120s, yields at 105s).
     *
     * @param float $startTime
     * @param int $timeoutSeconds
     * @return bool
     */
    protected function shouldYieldExecution(float $startTime, int $timeoutSeconds = 120): bool
    {
        $buffer = 15; 
        $elapsed = microtime(true) - $startTime;
        return $elapsed >= ($timeoutSeconds - $buffer);
    }

    /**
     * Periodically update the sync progress on the McpConnection to provide UI feedback.
     *
     * @param \App\Modules\MCP\Models\McpConnection $connection
     * @param int $processed
     * @param int $total
     */
    protected function reportSyncProgress(\App\Modules\MCP\Models\McpConnection $connection, int $processed, int $total = 0): void
    {
        if ($processed % 25 !== 0 && $processed !== $total) {
            return; // Throttle cache updates
        }

        // Periodically check if the sync has been cancelled by the user
        if ($connection->isSyncCancelled()) {
            throw new \App\Modules\MCP\Exceptions\SyncCancelledException("Sync job was cancelled by the user.");
        }

        if ($total <= 0) {
            // Indeterminate progress: asymptotically approach 99% based on items processed
            // e.g. at 100 items -> 63%, 300 items -> 95%, 500 items -> 99%
            $percentage = (int) (99 * (1 - exp(-$processed / 100)));
            $connection->updateSyncProgress(max(1, $percentage));
            return;
        }

        $percentage = (int) round(($processed / max(1, $total)) * 100);
        $connection->updateSyncProgress(min(100, $percentage));
    }
}
