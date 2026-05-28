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
        $stack->push($this->createRetryMiddleware());

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
        DB::table('mcp_sync_logs')->insert([
            'id' => (string) Str::uuid(),
            'mcp_connection_id' => $connectionId,
            'direction' => $direction,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => $status,
            'records_processed' => $processed,
            'records_failed' => $failed,
            'payload' => $payload ? json_encode($payload) : null,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'synced_at' => now(),
        ]);
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
}
