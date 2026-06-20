<?php

namespace App\Modules\MCP\Adapters;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\DataObjects\ConnectionTestResult;
use App\Modules\MCP\DataObjects\SyncResult;
use App\Modules\MCP\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generic adapter for any custom MCP provider.
 *
 * Configure behaviour via the connection's `settings` field:
 *
 *   base_url          (required) Base URL of the external API
 *   auth_type         bearer | api_key | basic | none  (default: none)
 *   auth_header       Header name when auth_type=api_key  (default: X-API-Key)
 *   sync_endpoint     Path called on sync  (default: /sync)
 *   sync_method       GET | POST  (default: GET)
 *   push_endpoint     Path called on push  (default: /push)
 *   push_method       POST | PUT | PATCH  (default: POST)
 *   test_endpoint     Path used to test the connection  (default: /)
 *   webhook_secret    HMAC-SHA256 secret for incoming webhook validation
 *   extra_headers     Key-value object of additional request headers
 *
 * Credentials (stored encrypted in `credentials` field):
 *   access_token      Used as bearer token when auth_type=bearer
 *   api_key           Used as API key when auth_type=api_key
 *   username          Used when auth_type=basic
 *   password          Used when auth_type=basic
 */
class CustomMcpAdapter extends BaseAdapter
{
    protected function getProviderName(): string
    {
        return 'custom';
    }

    public function authenticate(array $credentials): bool
    {
        return !empty($credentials['access_token']) || !empty($credentials['api_key']);
    }

    public function sync(string $connectionId, array $options = []): SyncResult
    {
        $connection = McpConnection::find($connectionId);
        if (!$connection) {
            return SyncResult::failure('Connection not found.');
        }

        $settings = $connection->settings ?? [];
        $baseUrl = $settings['base_url'] ?? null;

        if (!$baseUrl) {
            return SyncResult::failure('base_url is required in connection settings.');
        }

        $start = microtime(true);

        try {
            $this->setupClient(
                $baseUrl,
                $this->buildHeaders($connection),
            );

            $endpoint = $settings['sync_endpoint'] ?? '/sync';
            $method   = strtolower($settings['sync_method'] ?? 'GET');

            $response = $this->client->$method($endpoint);
            $status   = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                $body = json_decode((string) $response->getBody(), true) ?? [];
                $processed = count($body['data'] ?? $body['items'] ?? $body['results'] ?? []);

                $this->logSync(
                    $connectionId, 'inbound', 'custom_sync', null,
                    'success', $processed, 0, null, null,
                    (int) ((microtime(true) - $start) * 1000)
                );

                // Store raw response in metric_snapshots so DataViz can display it
                DB::table('metric_snapshots')->insert([
                    'id'              => (string) Str::uuid(),
                    'organization_id' => $connection->organization_id,
                    'source'          => $connection->provider,
                    'snapshot_date'   => now()->toDateString(),
                    'metrics'         => json_encode($body),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                return SyncResult::success($processed);
            }

            $err = "HTTP {$status} from {$baseUrl}{$endpoint}";
            $this->logSync($connectionId, 'inbound', 'custom_sync', null, 'failed', 0, 0, null, $err,
                (int) ((microtime(true) - $start) * 1000));

            return SyncResult::failure($err);
        } catch (\Exception $e) {
            $this->logSync($connectionId, 'inbound', 'custom_sync', null, 'failed', 0, 0, null,
                $e->getMessage(), (int) ((microtime(true) - $start) * 1000));

            return SyncResult::failure($e->getMessage());
        }
    }

    public function push(string $connectionId, array $data, array $options = []): SyncResult
    {
        $connection = McpConnection::find($connectionId);
        if (!$connection) {
            return SyncResult::failure('Connection not found.');
        }

        $settings = $connection->settings ?? [];
        $baseUrl  = $settings['base_url'] ?? null;

        if (!$baseUrl) {
            return SyncResult::failure('base_url is required in connection settings.');
        }

        $start = microtime(true);

        try {
            $this->setupClient($baseUrl, $this->buildHeaders($connection));

            $endpoint = $settings['push_endpoint'] ?? '/push';
            $method   = strtolower($settings['push_method'] ?? 'POST');

            $response = $this->client->$method($endpoint, ['json' => $data]);
            $status   = $response->getStatusCode();

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            if ($status >= 200 && $status < 300) {
                $this->logSync($connectionId, 'outbound', 'custom_push', null, 'success', 1, 0, $data, null, $durationMs);
                return SyncResult::success(1);
            }

            $err = "HTTP {$status} from {$baseUrl}{$endpoint}";
            $this->logSync($connectionId, 'outbound', 'custom_push', null, 'failed', 0, 1, $data, $err, $durationMs);
            return SyncResult::failure($err);
        } catch (\Exception $e) {
            $this->logSync($connectionId, 'outbound', 'custom_push', null, 'failed', 0, 1, $data,
                $e->getMessage(), (int) ((microtime(true) - $start) * 1000));
            return SyncResult::failure($e->getMessage());
        }
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        // Signature verification if webhook_secret is configured
        $connectionId = $request->route('connectionId');
        if ($connectionId) {
            $connection = McpConnection::find($connectionId);
            $secrets = [];
            if (!empty($connection?->settings['webhook_secret'])) {
                $secrets[] = $connection->settings['webhook_secret'];
            }
            if (!empty($connection?->settings['webhook_secrets']) && is_array($connection->settings['webhook_secrets'])) {
                $secrets = array_merge($secrets, $connection->settings['webhook_secrets']);
            }
            
            $secrets = array_unique(array_filter($secrets));

            if (!empty($secrets)) {
                $signature = $request->header('X-Signature') ?? $request->header('X-Hub-Signature-256');
                if (!$signature) {
                    return WebhookResult::failed('Missing webhook signature.');
                }

                $matched = false;
                $payload = $request->getContent();
                foreach ($secrets as $secret) {
                    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
                    if (hash_equals($expected, $signature)) {
                        $matched = true;
                        break;
                    }
                }
                
                if (!$matched) {
                    return WebhookResult::failed('Webhook signature mismatch.');
                }
            }
        }

        $payload = $request->all();
        Log::info("Custom MCP webhook received", ['connection_id' => $connectionId, 'payload_keys' => array_keys($payload)]);

        return WebhookResult::processed($payload);
    }

    public function testConnection(array $credentials): ConnectionTestResult
    {
        // credentials here may contain 'settings' injected by the controller
        $settings = $credentials['_settings'] ?? [];
        $baseUrl  = $settings['base_url'] ?? null;

        if (!$baseUrl) {
            return ConnectionTestResult::failure('base_url is required in settings.');
        }

        try {
            $headers = $this->buildHeadersFromCreds($credentials, $settings);
            $this->setupClient($baseUrl, $headers, ['timeout' => 10.0]);

            $endpoint = $settings['test_endpoint'] ?? '/';
            $response = $this->client->get($endpoint);
            $status   = $response->getStatusCode();

            if ($status >= 200 && $status < 400) {
                return ConnectionTestResult::success([
                    'provider'     => 'custom',
                    'base_url'     => $baseUrl,
                    'http_status'  => $status,
                    'connected_at' => now()->toIso8601String(),
                ]);
            }

            return ConnectionTestResult::failure("HTTP {$status} from {$baseUrl}{$endpoint}");
        } catch (\Exception $e) {
            return ConnectionTestResult::failure($e->getMessage());
        }
    }

    public function getAvailableScopes(): array
    {
        return [];
    }

    private function buildHeaders(McpConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $creds    = [];

        // Decrypt only the fields we need
        if ($connection->credentials) {
            foreach (['access_token', 'api_key', 'username', 'password'] as $key) {
                if (!empty($connection->credentials[$key])) {
                    try {
                        $creds[$key] = \Illuminate\Support\Facades\Crypt::decryptString(
                            $connection->credentials[$key]
                        );
                    } catch (\Exception $e) {
                        $creds[$key] = $connection->credentials[$key];
                    }
                }
            }
        }

        return $this->buildHeadersFromCreds($creds, $settings);
    }

    private function buildHeadersFromCreds(array $creds, array $settings): array
    {
        $headers = ['Accept' => 'application/json'];

        // Merge extra headers from settings
        foreach ($settings['extra_headers'] ?? [] as $name => $value) {
            $headers[$name] = $value;
        }

        $authType = $settings['auth_type'] ?? 'none';

        switch ($authType) {
            case 'bearer':
                $token = $creds['access_token'] ?? null;
                if ($token) {
                    $headers['Authorization'] = 'Bearer ' . $token;
                }
                break;

            case 'api_key':
                $apiKey    = $creds['api_key'] ?? null;
                $headerName = $settings['auth_header'] ?? 'X-API-Key';
                if ($apiKey) {
                    $headers[$headerName] = $apiKey;
                }
                break;

            case 'basic':
                $username = $creds['username'] ?? '';
                $password = $creds['password'] ?? '';
                if ($username) {
                    $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
                }
                break;
        }

        return $headers;
    }

    public function getCapabilities(): array
    {
        return [
            'custom_sync',
            'custom_push',
            'webhook_support'
        ];
    }

    public function getApiVersion(): string
    {
        return 'custom';
    }

    public function getCatalogueMetadata(): array
    {
        $metadata = parent::getCatalogueMetadata();
        $metadata['display_name'] = 'Custom Webhook / API';
        $metadata['description'] = 'Connect any generic REST API or webhook source.';
        $metadata['logo_url'] = null;
        $metadata['setup_guide_url'] = null;
        return $metadata;
    }

    public function extractWebhookTimestamp(Request $request): ?int
    {
        $ts = $request->header('X-Webhook-Timestamp') ?? $request->header('X-Timestamp');
        if ($ts && is_numeric($ts)) {
            return (int) $ts;
        }
        return null;
    }

    public function extractWebhookIdempotencyKey(Request $request): ?string
    {
        return $request->header('Idempotency-Key') ?? 
               $request->header('X-Idempotency-Key') ?? 
               $request->header('X-Request-ID') ?? 
               $request->input('idempotency_key') ?? 
               $request->input('event_id');
    }
}
