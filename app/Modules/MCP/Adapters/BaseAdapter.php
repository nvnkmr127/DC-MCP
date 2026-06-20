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
     * Initialize Guzzle client with credentials/base URI/headers.
     *
     * @param string $baseUri
     * @param array $headers
     * @param array $clientConfig
     * @return void
     */
    protected function setupClient(string $baseUri, array $headers = [], array $clientConfig = []): void
    {
        $stack = HandlerStack::create();
        $stack->push($this->createCircuitBreakerMiddleware(), 'circuit_breaker');
        $stack->push($this->createRetryMiddleware(), 'retry');

        $config = array_merge([
            'base_uri' => $baseUri,
            'headers'  => $headers,
            'handler'  => $stack,
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
    protected function decryptCredentials(array $credentials): array
    {
        $decrypted = [];
        foreach ($credentials as $key => $value) {
            try {
                $decrypted[$key] = Crypt::decryptString($value);
            } catch (\Exception $e) {
                // If it fails to decrypt, assume it's either not encrypted or treat as plain
                $decrypted[$key] = $value;
            }
        }
        return $decrypted;
    }

    /**
     * Encrypt values in a credentials array before saving.
     *
     * @param array $credentials
     * @return array
     */
    protected function encryptCredentials(array $credentials): array
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
        int $durationMs = 0
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
            $durationMs
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
    public function validateCredentialsFormat(array $credentials): void
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
    public function revokeCredentials(array $credentials): void
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
    public function testScopes(array $credentials, array $scopes): array
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
    public function getRateLimitStatus(array $credentials): ?array
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
     * Preview what data will be synced without actually syncing it.
     *
     * @param array $credentials
     * @param array $options
     * @return array
     */
    public function syncPreview(array $credentials, array $options = []): array
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
}
